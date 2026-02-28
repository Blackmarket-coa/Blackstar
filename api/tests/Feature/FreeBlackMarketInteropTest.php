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

class FreeBlackMarketInteropTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_option_webhook_is_idempotent_and_creates_listing(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');

        $creator = User::factory()->create();
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/delivery-option-selected.json')), true);
        $payload['payload']['created_by_user_id'] = $creator->id;

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, 'test-webhook-secret');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => $sig])
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => $sig])
            ->assertStatus(202);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 1);
        $this->assertDatabaseCount('shipment_board_listings', 1);

        $receipt = FbmInboundEventReceipt::query()->first();
        $this->assertSame('processed', $receipt->status);
    }

    public function test_failed_webhook_goes_to_dead_letter_after_retries(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.max_retries', 2);

        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/freeblackmarket/unsupported-event.json')), true);
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, 'test-webhook-secret');

        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => $sig])->assertStatus(202);
        $this->postJson('/api/webhooks/freeblackmarket', $payload, ['X-FBM-Signature' => $sig])->assertStatus(202);

        $receipt = FbmInboundEventReceipt::query()->first();
        $this->assertSame('dead_letter', $receipt->status);
        $this->assertSame(2, $receipt->attempts);
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

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-FBM-Signature')
                && $request->hasHeader('X-Correlation-ID', 'corr-claim-001')
                && $request['event_type'] === 'shipment.claimed';
        });
    }
}
