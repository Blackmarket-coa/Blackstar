<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\User;
use App\Services\GlobalDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentBoardListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_global_dispatcher_force_assigns_listing(): void
    {
        $creator = User::factory()->create();
        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claimed_by_node_id' => null,
        ]);

        $result = app(GlobalDispatchService::class)->autoAssign($listing);

        $this->assertNull($result);
        $this->assertNull($listing->refresh()->claimed_by_node_id);
        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->status);
    }

    public function test_only_eligible_nodes_can_claim_listing(): void
    {
        $eligibleNode = Node::factory()->create([
            'jurisdiction' => 'US',
            'transport_capabilities' => ['van', 'bike'],
        ]);
        $ineligibleNode = Node::factory()->create([
            'jurisdiction' => 'CA',
            'transport_capabilities' => ['bike'],
        ]);

        $creator = User::factory()->create();
        $eligibleUser = User::factory()->create(['node_id' => $eligibleNode->id]);
        $ineligibleUser = User::factory()->create(['node_id' => $ineligibleNode->id]);

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => 'US',
            'required_transport_capabilities' => ['van'],
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);

        $this->actingAs($ineligibleUser)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertForbidden();

        $this->actingAs($eligibleUser)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertOk()
            ->assertJsonPath('status', ShipmentBoardListing::STATUS_CLAIMED)
            ->assertJsonPath('claimed_by_node_id', $eligibleNode->id);
    }

    public function test_lifecycle_transitions_are_enforced(): void
    {
        $node = Node::factory()->create([
            'jurisdiction' => 'US',
            'transport_capabilities' => ['truck'],
        ]);
        $user = User::factory()->create(['node_id' => $node->id]);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => 'US',
            'required_transport_capabilities' => ['truck'],
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'in_transit'])
            ->assertOk()
            ->assertJsonPath('status', 'in_transit');

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'cancelled'])
            ->assertStatus(422);
    }

    public function test_bid_submission_for_bid_policy_listing(): void
    {
        $node = Node::factory()->create([
            'jurisdiction' => 'US',
            'transport_capabilities' => ['van'],
        ]);
        $user = User::factory()->create(['node_id' => $node->id]);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'claim_policy' => 'bid',
            'jurisdiction' => 'US',
            'required_transport_capabilities' => ['van'],
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/bids', [
                'amount' => 120.50,
                'currency' => 'USD',
                'note' => 'Can pickup in 30 mins',
            ])
            ->assertCreated()
            ->assertJsonPath('node_id', $node->id);
    }
}
