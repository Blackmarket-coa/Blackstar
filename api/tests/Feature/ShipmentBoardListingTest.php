<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBid;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use App\Services\GlobalDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentBoardListingTest extends TestCase
{
    use RefreshDatabase;


    public function test_creator_can_create_bounty_job_with_work_order_and_qa(): void
    {
        $creator = User::factory()->create();

        $response = $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings', [
                'source_order_ref' => 'BNTY-001',
                'claim_policy' => 'first_claim',
                'job_type' => 'virtual',
                'bounty_amount' => 350.75,
                'bounty_currency' => 'USD',
                'origin' => 'Remote',
                'destination' => 'Remote',
                'work_order' => 'Automate deployment pipeline and provide runbook.',
                'creator_qa_checklist' => ['pipeline passes', 'rollback tested'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('job_type', 'virtual')
            ->assertJsonPath('bounty_amount', '350.75')
            ->assertJsonPath('bounty_currency', 'USD')
            ->assertJsonPath('work_order', 'Automate deployment pipeline and provide runbook.')
            ->assertJsonPath('creator_qa_checklist.0', 'pipeline passes');
    }

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


    public function test_claim_updates_current_node_without_auto_reassignment_path(): void
    {
        $creator = User::factory()->create();

        $nodeA = Node::factory()->create(['jurisdiction' => 'US']);
        $nodeB = Node::factory()->create(['jurisdiction' => 'US']);
        $transport = TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $nodeA->transportClasses()->attach($transport->id);
        $nodeB->transportClasses()->attach($transport->id);

        $userA = User::factory()->create(['node_id' => $nodeA->id]);
        $userB = User::factory()->create(['node_id' => $nodeB->id]);

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'required_category' => 'ground',
            'required_subtype' => 'van',
        ]);

        $this->actingAs($userA)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertOk()
            ->assertJsonPath('current_node_id', $nodeA->id);

        $this->actingAs($userB)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/status', ['status' => 'in_transit'])
            ->assertForbidden();

        $listing->refresh();
        $this->assertSame($nodeA->id, $listing->current_node_id);
        $this->assertSame($nodeA->id, $listing->claimed_by_node_id);
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


    public function test_non_attested_node_cannot_claim_listing(): void
    {
        $node = Node::factory()->create([
            'is_active' => false,
            'transport_law_attestation_hash' => null,
            'platform_indemnification_attestation_hash' => null,
        ]);

        $transportClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'van',
            'weight_limit' => 1000,
            'range_limit' => 500,
        ]);
        $node->transportClasses()->attach($transportClass->id);

        $user = User::factory()->create(['node_id' => $node->id]);
        $creator = User::factory()->create();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'jurisdiction' => $node->jurisdiction,
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'status' => ShipmentBoardListing::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertForbidden();
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

    /**
     * Build an eligible node with a user, plus a bid-policy listing it can bid
     * on. Mirrors `test_bid_submission_for_bid_policy_listing`.
     */
    private function bidScenario(): array
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
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claim_policy' => 'bid',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_range_limit' => 50,
        ]);

        return [$node, $user, $creator, $listing];
    }

    public function test_bid_policy_listing_cannot_be_claimed_directly(): void
    {
        // Without this the claim_policy column was decorative: any eligible
        // node could take a bid listing outright and every bid was moot.
        [, $user, , $listing] = $this->bidScenario();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertStatus(422);

        $listing->refresh();
        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->status);
        $this->assertNull($listing->claimed_by_node_id);
    }

    public function test_first_claim_listing_is_unaffected_by_the_policy_gate(): void
    {
        [$node, $user, $creator] = $this->bidScenario();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claim_policy' => 'first_claim',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_range_limit' => 50,
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/claim')
            ->assertOk()
            ->assertJsonPath('claimed_by_node_id', $node->id);
    }

    public function test_poster_can_award_a_bid_and_it_becomes_the_claim(): void
    {
        [$node, $user, $creator, $listing] = $this->bidScenario();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/bids', [
                'amount' => 120.50,
                'currency' => 'USD',
            ])
            ->assertCreated();

        $bid = ShipmentBid::query()->where('shipment_board_listing_id', $listing->id)->firstOrFail();

        $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', [
                'bid_id' => $bid->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', ShipmentBoardListing::STATUS_CLAIMED)
            ->assertJsonPath('claimed_by_node_id', $node->id);

        $listing->refresh();
        $this->assertSame($bid->id, $listing->awarded_shipment_bid_id);
        $this->assertNotNull($listing->awarded_at);
        // Ownership state is explicit, as on the direct-claim path.
        $this->assertSame($node->id, $listing->current_node_id);
    }

    public function test_only_the_poster_can_award(): void
    {
        // Awarding is choosing between bids. A bidder awarding themselves would
        // be a direct claim wearing a different name.
        [, $user, , $listing] = $this->bidScenario();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/bids', ['amount' => 10])
            ->assertCreated();
        $bid = ShipmentBid::query()->where('shipment_board_listing_id', $listing->id)->firstOrFail();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertStatus(403);

        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->refresh()->status);
    }

    public function test_award_refuses_a_bid_from_another_listing(): void
    {
        // Scoped lookup: a bid id from elsewhere must read as "no such bid"
        // rather than award work across listings.
        [, $user, $creator, $listing] = $this->bidScenario();

        $other = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claim_policy' => 'bid',
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_range_limit' => 50,
        ]);

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $other->id . '/bids', ['amount' => 10])
            ->assertCreated();
        $foreignBid = ShipmentBid::query()->where('shipment_board_listing_id', $other->id)->firstOrFail();

        $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', [
                'bid_id' => $foreignBid->id,
            ])
            ->assertStatus(404);
    }

    public function test_award_refuses_a_first_claim_listing(): void
    {
        [, , $creator] = $this->bidScenario();

        $listing = ShipmentBoardListing::factory()->create([
            'created_by_user_id' => $creator->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claim_policy' => 'first_claim',
        ]);

        $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', [
                'bid_id' => 'whatever',
            ])
            ->assertStatus(422);
    }

    public function test_award_refuses_a_listing_that_is_no_longer_open(): void
    {
        [, $user, $creator, $listing] = $this->bidScenario();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/bids', ['amount' => 10])
            ->assertCreated();
        $bid = ShipmentBid::query()->where('shipment_board_listing_id', $listing->id)->firstOrFail();

        $listing->update(['status' => ShipmentBoardListing::STATUS_CANCELLED]);

        $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertStatus(422);
    }

    public function test_award_rechecks_eligibility_rather_than_trusting_the_bid(): void
    {
        // A node's capabilities can change between bidding and awarding, and
        // awarding assigns real work. The bid is a price, not a warrant.
        [$node, $user, $creator, $listing] = $this->bidScenario();

        $this->actingAs($user)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/bids', ['amount' => 10])
            ->assertCreated();
        $bid = ShipmentBid::query()->where('shipment_board_listing_id', $listing->id)->firstOrFail();

        $node->transportClasses()->detach();

        $this->actingAs($creator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertStatus(422);

        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->refresh()->status);
    }
}
