<?php

namespace Tests\Feature;

use App\Models\FbmInboundEventReceipt;
use App\Models\FbmOutboundEvent;
use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StagingE2EValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        config()->set('freeblackmarket.max_retries', 3);
        config()->set('freeblackmarket.retry_backoff_seconds', 0);
    }

    public function test_scenario_normal_full_lifecycle_with_correlation_consistency(): void
    {
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        $creator = User::factory()->create();
        [$claimerNode, $claimerUser] = $this->createEligibleNodeAndUser('US', 'ground', 'van');

        $webhookCorrelationId = 'corr-stg-normal-webhook-001';
        $claimCorrelationId = 'corr-stg-normal-claim-001';
        $transitCorrelationId = 'corr-stg-normal-transit-001';
        $deliveryCorrelationId = 'corr-stg-normal-delivered-001';

        $deliveryWebhookPayload = [
            'event_id' => 'evt-stg-normal-delivery-option-001',
            'event_type' => 'delivery.option.selected',
            'correlation_id' => $webhookCorrelationId,
            'payload' => [
                'source_order_ref' => 'order-stg-normal-001',
                'delivery_option' => 'federated_delivery_network',
                'created_by_user_id' => $creator->id,
                'jurisdiction' => 'US',
                'required_category' => 'ground',
                'required_subtype' => 'van',
            ],
        ];

        $deliveryWebhookResponse = $this->postJson(
            '/api/webhooks/freeblackmarket',
            $deliveryWebhookPayload,
            ['X-FBM-Signature' => $this->signWebhook($deliveryWebhookPayload)]
        )
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('correlation_id', $webhookCorrelationId)
            ->json();

        $listing = ShipmentBoardListing::query()->where('source_order_ref', 'order-stg-normal-001')->firstOrFail();
        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->status);

        $claimResponse = $this->actingAs($claimerUser)
            ->postJson(
                '/api/shipment-board-listings/' . $listing->id . '/claim',
                [],
                ['X-Correlation-ID' => $claimCorrelationId]
            )
            ->assertOk()
            ->assertJsonPath('status', ShipmentBoardListing::STATUS_CLAIMED)
            ->assertJsonPath('correlation_id', $claimCorrelationId)
            ->json();

        $inTransitResponse = $this->actingAs($claimerUser)
            ->postJson(
                '/api/shipment-board-listings/' . $listing->id . '/status',
                ['status' => 'in_transit'],
                ['X-Correlation-ID' => $transitCorrelationId]
            )
            ->assertOk()
            ->assertJsonPath('status', ShipmentBoardListing::STATUS_IN_TRANSIT)
            ->assertJsonPath('correlation_id', $transitCorrelationId)
            ->json();

        $deliveredResponse = $this->actingAs($claimerUser)
            ->postJson(
                '/api/shipment-board-listings/' . $listing->id . '/status',
                ['status' => 'delivered'],
                ['X-Correlation-ID' => $deliveryCorrelationId]
            )
            ->assertOk()
            ->assertJsonPath('status', ShipmentBoardListing::STATUS_DELIVERED)
            ->assertJsonPath('correlation_id', $deliveryCorrelationId)
            ->json();

        $this->assertDatabaseHas('fbm_inbound_event_receipts', [
            'event_id' => 'evt-stg-normal-delivery-option-001',
            'correlation_id' => $webhookCorrelationId,
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.claimed', 'correlation_id' => $claimCorrelationId]);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.in_transit', 'correlation_id' => $transitCorrelationId]);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.delivered', 'correlation_id' => $deliveryCorrelationId]);

        $this->assertSame($webhookCorrelationId, $deliveryWebhookResponse['correlation_id']);
        $this->assertSame($claimCorrelationId, $claimResponse['correlation_id']);
        $this->assertSame($transitCorrelationId, $inTransitResponse['correlation_id']);
        $this->assertSame($deliveryCorrelationId, $deliveredResponse['correlation_id']);
    }

    public function test_scenario_delayed_retry_dispatches_outbound_event_after_initial_failure(): void
    {
        Http::fakeSequence()
            ->push(['ok' => false], 500)
            ->push(['ok' => true], 200);

        $creator = User::factory()->create();
        [$claimerNode, $claimerUser] = $this->createEligibleNodeAndUser('US', 'ground', 'van');

        $payload = [
            'event_id' => 'evt-stg-retry-delivery-option-001',
            'event_type' => 'delivery.option.selected',
            'correlation_id' => 'corr-stg-retry-webhook-001',
            'payload' => [
                'source_order_ref' => 'order-stg-retry-001',
                'delivery_option' => 'federated_delivery_network',
                'created_by_user_id' => $creator->id,
                'jurisdiction' => 'US',
                'required_category' => 'ground',
                'required_subtype' => 'van',
            ],
        ];

        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => $this->signWebhook($payload)])
            ->assertStatus(202);

        $listing = ShipmentBoardListing::query()->where('source_order_ref', 'order-stg-retry-001')->firstOrFail();

        $this->actingAs($claimerUser)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim', [], ['X-Correlation-ID' => 'corr-stg-retry-claim-001'])
            ->assertOk()
            ->assertJsonPath('correlation_id', 'corr-stg-retry-claim-001');

        $failedEvent = FbmOutboundEvent::query()->where('event_type', 'shipment.claimed')->latest('id')->firstOrFail();
        $this->assertSame('failed', $failedEvent->status);
        $this->assertSame(1, $failedEvent->attempts);

        $this->postJson('/api/webhooks/freeblackmarket/retry')->assertOk();

        $retriedEvent = $failedEvent->fresh();
        $this->assertSame('dispatched', $retriedEvent->status);
        $this->assertSame(2, $retriedEvent->attempts);
        $this->assertSame('corr-stg-retry-claim-001', $retriedEvent->correlation_id);
    }

    public function test_scenario_cancellation_edge_cancels_in_transit_listing(): void
    {
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        $creator = User::factory()->create();
        [$claimerNode, $claimerUser] = $this->createEligibleNodeAndUser('US', 'ground', 'van');

        $deliveryPayload = [
            'event_id' => 'evt-stg-cancel-delivery-option-001',
            'event_type' => 'delivery.option.selected',
            'correlation_id' => 'corr-stg-cancel-webhook-001',
            'payload' => [
                'source_order_ref' => 'order-stg-cancel-001',
                'delivery_option' => 'federated_delivery_network',
                'created_by_user_id' => $creator->id,
                'jurisdiction' => 'US',
                'required_category' => 'ground',
                'required_subtype' => 'van',
            ],
        ];

        $this->postJson('/api/webhooks/freeblackmarket', $deliveryPayload, ['X-FBM-Signature' => $this->signWebhook($deliveryPayload)])
            ->assertStatus(202);

        $listing = ShipmentBoardListing::query()->where('source_order_ref', 'order-stg-cancel-001')->firstOrFail();

        $this->actingAs($claimerUser)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim', [], ['X-Correlation-ID' => 'corr-stg-cancel-claim-001'])
            ->assertOk();

        $this->actingAs($claimerUser)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'in_transit'], ['X-Correlation-ID' => 'corr-stg-cancel-transit-001'])
            ->assertOk()
            ->assertJsonPath('correlation_id', 'corr-stg-cancel-transit-001');

        $cancelPayload = [
            'event_id' => 'evt-stg-cancel-order-cancelled-001',
            'event_type' => 'order.cancelled',
            'correlation_id' => 'corr-stg-cancel-order-cancelled-001',
            'payload' => [
                'source_order_ref' => 'order-stg-cancel-001',
            ],
        ];

        $this->postJson('/api/webhooks/freeblackmarket', $cancelPayload, ['X-FBM-Signature' => $this->signWebhook($cancelPayload)])
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('correlation_id', 'corr-stg-cancel-order-cancelled-001');

        $this->assertSame(ShipmentBoardListing::STATUS_CANCELLED, $listing->fresh()->status);
        $this->assertDatabaseHas('fbm_inbound_event_receipts', [
            'event_id' => 'evt-stg-cancel-order-cancelled-001',
            'correlation_id' => 'corr-stg-cancel-order-cancelled-001',
            'status' => 'processed',
        ]);
        $this->assertDatabaseMissing('fbm_outbound_events', ['event_type' => 'shipment.delivered', 'payload->source_order_ref' => 'order-stg-cancel-001']);
    }

    protected function createEligibleNodeAndUser(string $jurisdiction, string $requiredCategory, string $requiredSubtype): array
    {
        $node = Node::factory()->create(['jurisdiction' => $jurisdiction]);
        $transportClass = TransportClass::factory()->create(['category' => $requiredCategory, 'subtype' => $requiredSubtype]);
        $node->transportClasses()->attach($transportClass->id);
        $user = User::factory()->create(['node_id' => $node->id]);

        return [$node, $user];
    }

    protected function signWebhook(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'test-webhook-secret');
    }
}
