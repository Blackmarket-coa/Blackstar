<?php

namespace Tests\Feature;

use App\Models\FbmInboundEventReceipt;
use App\Models\FbmOutboundEvent;
use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use App\Services\FreeBlackMarket\InboundEventProcessor;
use App\Services\FreeBlackMarket\OutboundEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FreeBlackMarketInteropTest extends TestCase
{
    use RefreshDatabase;

    protected function signedHeaders(string $json, string $secret, ?int $timestamp = null): array
    {
        $ts = (string) ($timestamp ?? now()->getTimestamp());

        return [
            'X-FBM-Timestamp' => $ts,
            'X-FBM-Signature' => hash_hmac('sha256', $ts . '.' . $json, $secret),
        ];
    }

    public function test_delivery_option_webhook_is_idempotent_and_creates_listing(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');

        $creator = User::factory()->create();
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/delivery-option-selected.json')), true);
        $payload['payload']['created_by_user_id'] = $creator->id;

        $json = json_encode($payload);
        $headers = $this->signedHeaders($json, 'test-webhook-secret');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, $headers)
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, $headers)
            ->assertStatus(202);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 1);
        $this->assertDatabaseCount('shipment_board_listings', 1);

        $receipt = FbmInboundEventReceipt::query()->first();
        $this->assertSame('processed', $receipt->status);
    }

    public function test_delivery_option_without_creator_is_owned_by_the_service_account(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');

        $serviceAccount = User::factory()->create();
        config()->set('freeblackmarket.system_user_id', $serviceAccount->id);

        // FBM omits created_by_user_id by contract — it has no Blackstar user
        // identity to send.
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/delivery-option-selected.json')), true);
        unset($payload['payload']['created_by_user_id']);
        $payload['event_id'] = 'evt-delivery-no-creator-001';

        $json = json_encode($payload);
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($json, 'test-webhook-secret'))
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed');

        $listing = ShipmentBoardListing::query()->where('source_order_ref', 'ORD-2001')->first();
        $this->assertNotNull($listing);
        $this->assertSame($serviceAccount->id, $listing->created_by_user_id);
    }

    public function test_delivery_option_without_creator_or_service_account_dead_letters_actionably(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.system_user_id', null);
        config()->set('freeblackmarket.max_retries', 1);

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/delivery-option-selected.json')), true);
        unset($payload['payload']['created_by_user_id']);
        $payload['event_id'] = 'evt-delivery-no-creator-002';

        $json = json_encode($payload);
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($json, 'test-webhook-secret'))
            ->assertStatus(202);

        $receipt = FbmInboundEventReceipt::query()->where('event_id', 'evt-delivery-no-creator-002')->first();
        $this->assertSame('dead_letter', $receipt->status);
        $this->assertStringContainsString('FBM_SYSTEM_USER_ID', $receipt->last_error);
        $this->assertDatabaseCount('shipment_board_listings', 0);
    }

    public function test_failed_webhook_goes_to_dead_letter_after_retries(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.max_retries', 2);

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/unsupported-event.json')), true);
        $json = json_encode($payload);
        $headers = $this->signedHeaders($json, 'test-webhook-secret');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, $headers)->assertStatus(202);
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $headers)->assertStatus(202);

        $receipt = FbmInboundEventReceipt::query()->first();
        $this->assertSame('dead_letter', $receipt->status);
        $this->assertSame(2, $receipt->attempts);
    }

    public function test_webhook_returns_503_when_secret_is_not_configured(): void
    {
        config()->set('freeblackmarket.webhook_secret', null);

        $payload = ['event_id' => 'evt-unconfigured', 'event_type' => 'order.created', 'payload' => []];
        $json = json_encode($payload);

        // Signed with the literal default that used to ship in config — the
        // exact credential this change revokes.
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($json, 'fbm_webhook_secret'))
            ->assertStatus(503);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 0);
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');

        $payload = ['event_id' => 'evt-bad-sig', 'event_type' => 'order.created', 'payload' => []];
        $json = json_encode($payload);

        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($json, 'wrong-secret'))
            ->assertStatus(401);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 0);
    }

    public function test_webhook_rejects_stale_timestamp_as_replay(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.signature_tolerance_seconds', 300);

        $payload = ['event_id' => 'evt-replay', 'event_type' => 'order.created', 'payload' => []];
        $json = json_encode($payload);
        $staleHeaders = $this->signedHeaders($json, 'test-webhook-secret', now()->getTimestamp() - 301);

        // Correctly signed, but outside the tolerance window: a captured
        // request replayed later must not authenticate.
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $staleHeaders)
            ->assertStatus(401);

        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => hash_hmac('sha256', $json, 'test-webhook-secret')])
            ->assertStatus(401);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 0);
    }

    public function test_emits_signed_outbound_lifecycle_event_with_correlation_id(): void
    {
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');

        Http::fake([
            'https://fbm.example/events' => Http::response(['ok' => true], 200),
        ]);

        $creator = User::factory()->create();
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $node->transportClasses()->attach($transportClass->id);
        $claimer = User::factory()->create(['node_id' => $node->id]);

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
        ]);

        $this->actingAs($claimer)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim', [], ['X-Correlation-ID' => 'corr-claim-001'])
            ->assertOk();

        $outbound = FbmOutboundEvent::query()->latest('created_at')->first();
        $this->assertNotNull($outbound);
        $this->assertSame('shipment.claimed', $outbound->event_type);
        $this->assertSame('corr-claim-001', $outbound->correlation_id);
        $this->assertSame('dispatched', $outbound->status);

        Http::assertSent(function ($request) use ($outbound) {
            $timestamp = $request->header('X-FBM-Timestamp')[0] ?? '';
            $signature = $request->header('X-FBM-Signature')[0] ?? '';

            // The signature must cover the exact bytes on the wire — the full
            // envelope, not just the payload member. event_id rides inside the
            // signed body and matches the outbound row, so receivers get a
            // retry-stable dedupe key.
            return $timestamp !== ''
                && hash_equals(hash_hmac('sha256', $timestamp . '.' . $request->body(), 'test-outbound-secret'), $signature)
                && $request->hasHeader('X-Correlation-ID', 'corr-claim-001')
                && $request['event_type'] === 'shipment.claimed'
                && $request['event_id'] === $outbound->id;
        });
    }

    public function test_outbound_connection_failure_is_absorbed_into_retry_not_thrown(): void
    {
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        config()->set('freeblackmarket.max_retries', 3);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('CONNECT tunnel failed');
        });

        $publisher = app(OutboundEventPublisher::class);
        $event = $publisher->queueAndDispatch('shipment.claimed', ['source_order_ref' => 'ref-net'], 'corr-net');

        // The user-facing action that triggered the event must not blow up;
        // the delivery lands in the retry lane instead.
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('Connection error', $event->last_error);
        $this->assertNotNull($event->next_attempt_at);
    }

    public function test_outbound_dispatch_fails_closed_without_a_secret(): void
    {
        config()->set('freeblackmarket.outbound_secret', null);
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        config()->set('freeblackmarket.max_retries', 1);

        Http::fake();

        $publisher = app(OutboundEventPublisher::class);
        $event = $publisher->queueAndDispatch('shipment.claimed', ['source_order_ref' => 'ref-1'], 'corr-x');

        $this->assertSame('dead_letter', $event->status);
        $this->assertSame('Missing FBM outbound secret.', $event->last_error);
        Http::assertNothingSent();
    }

    public function test_retry_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/webhooks/freeblackmarket/retry')->assertStatus(401);
    }

    public function test_retry_endpoint_invokes_retry_processors_and_returns_allowlisted_response(): void
    {
        $processor = Mockery::mock(InboundEventProcessor::class);
        $processor->shouldReceive('retryFailed')->once();
        $this->app->instance(InboundEventProcessor::class, $processor);

        $publisher = Mockery::mock(OutboundEventPublisher::class);
        $publisher->shouldReceive('retryPending')->once();
        $this->app->instance(OutboundEventPublisher::class, $publisher);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/webhooks/freeblackmarket/retry', [
                'unexpected' => 'input',
                'private_coordination' => 'do-not-echo',
            ])->assertOk()->json();

        $this->assertSame(['status' => 'ok'], $response);
    }
}
