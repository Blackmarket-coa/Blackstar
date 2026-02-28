<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
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
        $eligibleNode = Node::factory()->create(['jurisdiction' => 'US']);
        $ineligibleNode = Node::factory()->create(['jurisdiction' => 'US']);

        $eligibleClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'van',
            'weight_limit' => 900,
            'range_limit' => 450,
            'hazard_capability' => true,
            'regulatory_class' => 'HZ-A',
            'insurance_required_flag' => true,
        ]);

        $ineligibleClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'bike',
            'weight_limit' => 20,
            'range_limit' => 40,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => false,
        ]);

        $eligibleNode->transportClasses()->attach($eligibleClass->id);
        $ineligibleNode->transportClasses()->attach($ineligibleClass->id);

        $creator = User::factory()->create();
        $eligibleUser = User::factory()->create(['node_id' => $eligibleNode->id]);
        $ineligibleUser = User::factory()->create(['node_id' => $ineligibleNode->id]);

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 500,
            'required_range_limit' => 300,
            'requires_hazard_capability' => true,
            'required_regulatory_class' => 'HZ-A',
            'insurance_required_flag' => true,
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
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'truck',
            'weight_limit' => 5000,
            'range_limit' => 900,
        ]);
        $node->transportClasses()->attach($transportClass->id);

        $user = User::factory()->create(['node_id' => $node->id]);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'truck',
            'required_weight_limit' => 1000,
            'required_range_limit' => 100,
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
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'van',
        ]);
        $node->transportClasses()->attach($transportClass->id);

        $user = User::factory()->create(['node_id' => $node->id]);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'claim_policy' => 'bid',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_range_limit' => 50,
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
