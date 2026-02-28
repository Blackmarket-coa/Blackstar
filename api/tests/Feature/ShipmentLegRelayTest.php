<?php

namespace Tests\Feature;

use App\Models\FbmOutboundEvent;
use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\ShipmentLeg;
use App\Models\TransportClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShipmentLegRelayTest extends TestCase
{
    use RefreshDatabase;

    protected function makeNodeWithUser(string $jurisdiction = 'US'): array
    {
        $node = Node::factory()->create(['jurisdiction' => $jurisdiction]);
        $tc = TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $node->transportClasses()->attach($tc->id);
        $user = User::factory()->create(['node_id' => $node->id]);

        return [$node, $user];
    }

    protected function makeClaimedListing(string $jurisdiction = 'US'): array
    {
        [$nodeA, $userA] = $this->makeNodeWithUser($jurisdiction);
        [$nodeB, $userB] = $this->makeNodeWithUser($jurisdiction);
        [$nodeC, $userC] = $this->makeNodeWithUser($jurisdiction);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => $jurisdiction,
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'status' => ShipmentBoardListing::STATUS_CLAIMED,
            'claimed_by_node_id' => $nodeA->id,
        ]);

        return [$listing, $nodeA, $nodeB, $nodeC, $userA, $userB, $userC];
    }

    public function test_two_leg_shipment_completes_and_marks_listing_delivered(): void
    {
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        [$listing, $nodeA, $nodeB, $nodeC, $userA] = $this->makeClaimedListing();

        $leg1 = ShipmentLeg::factory()->create([
            'shipment_board_listing_id' => $listing->id,
            'sequence' => 1,
            'from_node_id' => $nodeA->id,
            'to_node_id' => $nodeB->id,
        ]);

        $leg2 = ShipmentLeg::factory()->create([
            'shipment_board_listing_id' => $listing->id,
            'sequence' => 2,
            'from_node_id' => $nodeB->id,
            'to_node_id' => $nodeC->id,
        ]);

        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'in_transit'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'handed_off', 'proof_of_handoff_hash' => 'proof-leg1'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'completed', 'settlement_ref' => 'set-leg1'])->assertOk();

        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'in_transit'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'handed_off', 'proof_of_handoff_hash' => 'proof-leg2'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'completed', 'settlement_ref' => 'set-leg2'])->assertOk();

        $this->assertSame(ShipmentBoardListing::STATUS_DELIVERED, $listing->fresh()->status);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.leg.updated']);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.leg.handoff_proof']);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.delivered']);
    }

    public function test_three_leg_shipment_sequence_rules_and_completion(): void
    {
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        [$listing, $nodeA, $nodeB, $nodeC, $userA] = $this->makeClaimedListing();
        $nodeD = Node::factory()->create(['jurisdiction' => 'US']);

        $leg1 = ShipmentLeg::factory()->create(['shipment_board_listing_id' => $listing->id, 'sequence' => 1, 'from_node_id' => $nodeA->id, 'to_node_id' => $nodeB->id]);
        $leg2 = ShipmentLeg::factory()->create(['shipment_board_listing_id' => $listing->id, 'sequence' => 2, 'from_node_id' => $nodeB->id, 'to_node_id' => $nodeC->id]);
        $leg3 = ShipmentLeg::factory()->create(['shipment_board_listing_id' => $listing->id, 'sequence' => 3, 'from_node_id' => $nodeC->id, 'to_node_id' => $nodeD->id]);

        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'in_transit'])->assertStatus(422);

        foreach ([$leg1, $leg2, $leg3] as $index => $leg) {
            $i = $index + 1;
            $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg->id}", ['status' => 'in_transit'])->assertOk();
            $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg->id}", ['status' => 'handed_off', 'proof_of_handoff_hash' => "proof-{$i}"])->assertOk();
            $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg->id}", ['status' => 'completed', 'settlement_ref' => "set-{$i}"])->assertOk();
        }

        $this->assertSame(ShipmentBoardListing::STATUS_DELIVERED, $listing->fresh()->status);
        $this->assertGreaterThanOrEqual(1, FbmOutboundEvent::query()->where('event_type', 'shipment.delivered')->count());
    }

    public function test_failed_handoff_moves_listing_to_disputed_path(): void
    {
        config()->set('freeblackmarket.outbound_url', 'https://fbm.example/events');
        Http::fake(['https://fbm.example/events' => Http::response(['ok' => true], 200)]);

        [$listing, $nodeA, $nodeB, $nodeC, $userA] = $this->makeClaimedListing();

        $leg1 = ShipmentLeg::factory()->create(['shipment_board_listing_id' => $listing->id, 'sequence' => 1, 'from_node_id' => $nodeA->id, 'to_node_id' => $nodeB->id]);
        $leg2 = ShipmentLeg::factory()->create(['shipment_board_listing_id' => $listing->id, 'sequence' => 2, 'from_node_id' => $nodeB->id, 'to_node_id' => $nodeC->id]);

        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'in_transit'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'handed_off', 'proof_of_handoff_hash' => 'proof-ok'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg1->id}", ['status' => 'completed'])->assertOk();

        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'in_transit'])->assertOk();
        $this->actingAs($userA)->patchJson("/api/shipment-board-listings/{$listing->id}/legs/{$leg2->id}", ['status' => 'failed', 'proof_of_handoff_hash' => 'proof-failed'])->assertOk();

        $this->assertSame(ShipmentBoardListing::STATUS_DISPUTED, $listing->fresh()->status);
        $this->assertDatabaseHas('fbm_outbound_events', ['event_type' => 'shipment.disputed']);
    }
}
