<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorVisibilityContractTest extends TestCase
{
    use RefreshDatabase;

    private const VENDOR_LISTING_FIELDS = [
        'id',
        'source_order_ref',
        'status',
        'claim_policy',
        'jurisdiction',
        'required_category',
        'required_subtype',
        'required_weight_limit',
        'required_range_limit',
        'requires_hazard_capability',
        'required_regulatory_class',
        'insurance_required_flag',
        'required_transport_capabilities',
        'claimed_by_node_id',
        'claimed_at',
        'in_transit_at',
        'delivered_at',
        'disputed_at',
        'cancelled_at',
        'created_at',
        'updated_at',
    ];

    private const VENDOR_LISTING_FIELDS_WITH_CORRELATION = [
        ...self::VENDOR_LISTING_FIELDS,
        'correlation_id',
    ];

    private const OUTBOUND_EVENT_ENVELOPE_FIELDS = ['event_type', 'payload', 'correlation_id'];
    private const OUTBOUND_EVENT_PAYLOAD_FIELDS = ['shipment_listing_id', 'source_order_ref', 'claimed_by_node_id', 'status'];

    private const DENYLIST_KEYS = [
        'created_by_user_id',
        'route_plan',
        'route_polyline',
        'internal_notes',
        'dispatch_internal_id',
        'private_coordination',
        'node_private_key',
        'telemetry',
        'telemetry_trace',
        'telemetry_snapshot',
        'gps_trace',
        'gps_history',
        'vehicle_position',
        'engine_temp',
        'engine_rpm',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        config()->set('freeblackmarket.outbound_secret', 'test-outbound-secret');
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
    }

    public function test_vendor_api_responses_use_allowlisted_fields_and_deny_internal_keys(): void
    {
        $creator = User::factory()->create();
        [$node, $user] = $this->makeEligibleNodeUser();

        $storeResponse = $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings', [
                'source_order_ref' => 'order-vendor-visibility-001',
                'jurisdiction' => 'US',
                'required_category' => 'ground',
                'required_subtype' => 'van',
            ])
            ->assertCreated()
            ->json();

        $this->assertExactKeys($storeResponse, self::VENDOR_LISTING_FIELDS);
        $this->assertDenylistAbsent($storeResponse, self::DENYLIST_KEYS);

        $eligibleResponse = $this->actingAs($user)
            ->getJson('/api/shipment-board-listings/eligible')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($eligibleResponse);
        $this->assertExactKeys($eligibleResponse[0], self::VENDOR_LISTING_FIELDS);
        $this->assertDenylistAbsent($eligibleResponse[0], self::DENYLIST_KEYS);

        $claimResponse = $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $storeResponse['id'] . '/claim', [], ['X-Correlation-ID' => 'corr-vendor-claim-001'])
            ->assertOk()
            ->json();

        $this->assertExactKeys($claimResponse, self::VENDOR_LISTING_FIELDS_WITH_CORRELATION);
        $this->assertSame('corr-vendor-claim-001', $claimResponse['correlation_id']);
        $this->assertDenylistAbsent($claimResponse, self::DENYLIST_KEYS);

        $statusResponse = $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $storeResponse['id'] . '/status', ['status' => 'in_transit'], ['X-Correlation-ID' => 'corr-vendor-transit-001'])
            ->assertOk()
            ->json();

        $this->assertExactKeys($statusResponse, self::VENDOR_LISTING_FIELDS_WITH_CORRELATION);
        $this->assertSame('corr-vendor-transit-001', $statusResponse['correlation_id']);
        $this->assertDenylistAbsent($statusResponse, self::DENYLIST_KEYS);
    }

    public function test_webhook_api_response_is_allowlisted(): void
    {
        $creator = User::factory()->create();
        $body = [
            'event_id' => 'evt-visibility-webhook-001',
            'event_type' => 'delivery.option.selected',
            'correlation_id' => 'corr-visibility-webhook-001',
            'payload' => [
                'source_order_ref' => 'order-visibility-webhook-001',
                'delivery_option' => 'federated_delivery_network',
                'created_by_user_id' => $creator->id,
                'jurisdiction' => 'US',
                'required_category' => 'ground',
                'required_subtype' => 'van',
            ],
        ];

        $response = $this->postJson('/api/webhooks/freeblackmarket', $body, [
            'X-FBM-Signature' => $this->signWebhook($body),
        ])
            ->assertStatus(202)
            ->json();

        $this->assertExactKeys($response, ['status', 'event_id', 'correlation_id', 'attempts']);
        $this->assertDenylistAbsent($response, self::DENYLIST_KEYS);
    }

    public function test_outbound_event_payload_contract_for_claimed_in_transit_delivered_disputed(): void
    {
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        $creator = User::factory()->create();
        [$node, $user] = $this->makeEligibleNodeUser();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'source_order_ref' => 'order-visibility-outbound-001',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);

        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listing->id . '/claim', [], ['X-Correlation-ID' => 'corr-evt-claimed-001'])->assertOk();
        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'in_transit'], ['X-Correlation-ID' => 'corr-evt-transit-001'])->assertOk();
        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'disputed'], ['X-Correlation-ID' => 'corr-evt-disputed-001'])->assertOk();

        $listingDelivered = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'source_order_ref' => 'order-visibility-outbound-002',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);

        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listingDelivered->id . '/claim', [], ['X-Correlation-ID' => 'corr-evt-claimed-002'])->assertOk();
        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listingDelivered->id . '/status', ['status' => 'in_transit'], ['X-Correlation-ID' => 'corr-evt-transit-002'])->assertOk();
        $this->actingAs($user)->postJson('/api/shipment-board-listings/' . $listingDelivered->id . '/status', ['status' => 'delivered'], ['X-Correlation-ID' => 'corr-evt-delivered-001'])->assertOk();

        $sentBodies = [];
        Http::assertSent(function ($request) use (&$sentBodies) {
            $sentBodies[] = $request->data();

            return true;
        });

        $expectedByType = [
            'shipment.claimed' => 'corr-evt-claimed-001',
            'shipment.in_transit' => 'corr-evt-transit-001',
            'shipment.disputed' => 'corr-evt-disputed-001',
            'shipment.delivered' => 'corr-evt-delivered-001',
        ];

        foreach ($expectedByType as $type => $correlationId) {
            $matching = collect($sentBodies)->first(fn (array $body) => ($body['event_type'] ?? null) === $type && ($body['correlation_id'] ?? null) === $correlationId);

            $this->assertNotNull($matching, "Missing outbound payload for {$type} with {$correlationId}");
            $this->assertExactKeys($matching, self::OUTBOUND_EVENT_ENVELOPE_FIELDS);
            $this->assertExactKeys($matching['payload'], self::OUTBOUND_EVENT_PAYLOAD_FIELDS);
            $this->assertDenylistAbsent($matching, self::DENYLIST_KEYS);
        }
    }

    private function makeEligibleNodeUser(): array
    {
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $node->transportClasses()->attach($transportClass->id);

        return [$node, User::factory()->create(['node_id' => $node->id])];
    }

    private function signWebhook(array $body): string
    {
        return hash_hmac('sha256', json_encode($body), 'test-webhook-secret');
    }

    private function assertExactKeys(array $payload, array $approvedKeys): void
    {
        $actual = array_keys($payload);
        sort($actual);
        $expected = $approvedKeys;
        sort($expected);

        $this->assertSame($expected, $actual, 'Payload keys differ from approved allowlist.');
    }

    private function assertDenylistAbsent(array $payload, array $deniedKeys): void
    {
        array_walk_recursive($payload, function ($value, $key) use ($deniedKeys) {
            $this->assertNotContains($key, $deniedKeys, "Denied key [{$key}] should not be present in vendor payloads.");
        });

        foreach ($deniedKeys as $deniedKey) {
            $this->assertArrayNotHasKey($deniedKey, $payload, "Denied top-level key [{$deniedKey}] should not be present.");
        }
    }
}
