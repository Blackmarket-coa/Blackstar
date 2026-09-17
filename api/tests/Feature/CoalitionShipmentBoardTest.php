<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeCoalitionMembership;
use App\Models\ShipmentBid;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use App\Services\FreeBlackMarket\InboundEventProcessor;
use App\Services\ShipmentEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coalition goods drives on the shipment board.
 *
 * Two things have to hold at once: a coalition's freight reaches that
 * coalition's own nodes and nobody else's, and every ordinary listing keeps
 * behaving exactly as it did — a board that narrowed everything to coalition
 * members would break the network it runs on.
 */
class CoalitionShipmentBoardTest extends TestCase
{
    use RefreshDatabase;

    private const COALITION = 'coa_westside';

    private function capableNode(array $attributes = []): Node
    {
        $node = Node::factory()->create(array_merge([
            'jurisdiction' => 'US',
            'is_active' => true,
        ], $attributes));

        // Transport classes are unique on (category, subtype), so several
        // nodes in one test share the class rather than each making their own.
        $class = TransportClass::firstWhere(['category' => 'ground', 'subtype' => 'van'])
            ?? TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van']);
        $node->transportClasses()->attach($class->id);

        return $node;
    }

    private function listing(array $attributes = []): ShipmentBoardListing
    {
        return ShipmentBoardListing::factory()->create(array_merge([
            'created_by_user_id' => User::factory()->create()->id,
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
        ], $attributes));
    }

    private function joinCoalition(Node $node, string $role = NodeCoalitionMembership::ROLE_MEMBER): void
    {
        NodeCoalitionMembership::create([
            'node_id' => $node->id,
            'coalition_ref' => self::COALITION,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function eligible(Node $node, ShipmentBoardListing $listing): bool
    {
        return app(ShipmentEligibilityService::class)->isNodeEligibleForListing($node, $listing);
    }

    public function test_coalition_member_is_eligible_for_its_coalitions_drive(): void
    {
        $node = $this->capableNode();
        $this->joinCoalition($node);

        $listing = $this->listing(['coalition_ref' => self::COALITION, 'drive_ref' => 'camp_1']);

        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_outsider_is_not_eligible_for_a_coalition_drive(): void
    {
        // Identical capability, jurisdiction and standing — the only difference
        // is membership, which is the whole point of a coalition drive.
        $node = $this->capableNode();

        $listing = $this->listing(['coalition_ref' => self::COALITION]);

        $this->assertFalse($this->eligible($node, $listing));
    }

    public function test_inactive_membership_does_not_carry_eligibility(): void
    {
        $node = $this->capableNode();
        NodeCoalitionMembership::create([
            'node_id' => $node->id,
            'coalition_ref' => self::COALITION,
            'role' => NodeCoalitionMembership::ROLE_MEMBER,
            'is_active' => false,
        ]);

        $listing = $this->listing(['coalition_ref' => self::COALITION]);

        $this->assertFalse($this->eligible($node, $listing));
    }

    public function test_membership_of_another_coalition_does_not_carry_over(): void
    {
        $node = $this->capableNode();
        NodeCoalitionMembership::create([
            'node_id' => $node->id,
            'coalition_ref' => 'coa_eastside',
            'role' => NodeCoalitionMembership::ROLE_COORDINATOR,
            'is_active' => true,
        ]);

        $listing = $this->listing(['coalition_ref' => self::COALITION]);

        $this->assertFalse($this->eligible($node, $listing));
    }

    public function test_ordinary_listings_are_unaffected_by_coalition_membership(): void
    {
        $member = $this->capableNode();
        $this->joinCoalition($member);
        $outsider = $this->capableNode();

        $listing = $this->listing();

        $this->assertTrue($this->eligible($member, $listing));
        $this->assertTrue($this->eligible($outsider, $listing));
    }

    public function test_coalition_role_confers_no_award_authority(): void
    {
        // A coordinator is a coalition's own designation and grants nothing on
        // the board. Awarding stays the poster's act, so a coordinator cannot
        // award a listing they did not post — which also means they cannot
        // award their own coalition's freight to a node of their choosing.
        $serviceAccount = User::factory()->create();

        $coordinatorNode = $this->capableNode();
        $this->joinCoalition($coordinatorNode, NodeCoalitionMembership::ROLE_COORDINATOR);
        $coordinator = User::factory()->create(['node_id' => $coordinatorNode->id]);

        $bidderNode = $this->capableNode();
        $this->joinCoalition($bidderNode);

        $listing = $this->listing([
            'created_by_user_id' => $serviceAccount->id,
            'claim_policy' => 'bid',
            'coalition_ref' => self::COALITION,
            'drive_ref' => 'camp_1',
        ]);
        $bid = ShipmentBid::create([
            'shipment_board_listing_id' => $listing->id,
            'node_id' => $bidderNode->id,
            'amount' => 120.00,
            'currency' => 'USD',
        ]);

        $this->actingAs($coordinator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertForbidden();

        $listing->refresh();
        $this->assertSame(ShipmentBoardListing::STATUS_OPEN, $listing->status);
    }

    public function test_the_poster_can_still_award_a_coalition_drive(): void
    {
        // The narrowing must not break the ordinary path: whoever posted the
        // listing awards it, coalition ref or not.
        $poster = User::factory()->create();

        $bidderNode = $this->capableNode();
        $this->joinCoalition($bidderNode);

        $listing = $this->listing([
            'created_by_user_id' => $poster->id,
            'claim_policy' => 'bid',
            'coalition_ref' => self::COALITION,
            'drive_ref' => 'camp_1',
        ]);
        $bid = ShipmentBid::create([
            'shipment_board_listing_id' => $listing->id,
            'node_id' => $bidderNode->id,
            'amount' => 120.00,
            'currency' => 'USD',
        ]);

        $this->actingAs($poster)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertOk();

        $listing->refresh();
        $this->assertSame(ShipmentBoardListing::STATUS_CLAIMED, $listing->status);
        $this->assertSame($bidderNode->id, $listing->claimed_by_node_id);
        $this->assertSame($bid->id, $listing->awarded_shipment_bid_id);
    }

    public function test_plain_member_cannot_award_a_coalition_drive(): void
    {
        $serviceAccount = User::factory()->create();

        $memberNode = $this->capableNode();
        $this->joinCoalition($memberNode);
        $member = User::factory()->create(['node_id' => $memberNode->id]);

        $listing = $this->listing([
            'created_by_user_id' => $serviceAccount->id,
            'claim_policy' => 'bid',
            'coalition_ref' => self::COALITION,
        ]);
        $bid = ShipmentBid::create([
            'shipment_board_listing_id' => $listing->id,
            'node_id' => $memberNode->id,
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->actingAs($member)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertForbidden();
    }

    public function test_a_coalition_member_cannot_award_other_peoples_listings(): void
    {
        $poster = User::factory()->create();

        $coordinatorNode = $this->capableNode();
        $this->joinCoalition($coordinatorNode, NodeCoalitionMembership::ROLE_COORDINATOR);
        $coordinator = User::factory()->create(['node_id' => $coordinatorNode->id]);

        $bidderNode = $this->capableNode();

        // No coalition_ref: an ordinary listing, and coordination grants
        // nothing over it.
        $listing = $this->listing([
            'created_by_user_id' => $poster->id,
            'claim_policy' => 'bid',
        ]);
        $bid = ShipmentBid::create([
            'shipment_board_listing_id' => $listing->id,
            'node_id' => $bidderNode->id,
            'amount' => 90.00,
            'currency' => 'USD',
        ]);

        $this->actingAs($coordinator)
            ->postJson('/api/shipment-board-listings/' . $listing->id . '/award', ['bid_id' => $bid->id])
            ->assertForbidden();
    }

    public function test_inbound_drive_event_carries_the_coalition_refs_onto_the_listing(): void
    {
        config(['freeblackmarket.system_user_id' => User::factory()->create()->id]);

        app(InboundEventProcessor::class)->process([
            'event_id' => 'evt_coalition_1',
            'event_type' => 'delivery.option.selected',
            'payload' => [
                'source_order_ref' => 'ord_coalition_1',
                'delivery_option' => 'federated_delivery_network',
                'claim_policy' => 'bid',
                'job_type' => 'physical',
                'coalition_ref' => self::COALITION,
                'drive_ref' => 'camp_1',
            ],
        ], 'corr_coalition_1');

        $listing = ShipmentBoardListing::where('source_order_ref', 'ord_coalition_1')->firstOrFail();
        $this->assertSame(self::COALITION, $listing->coalition_ref);
        $this->assertSame('camp_1', $listing->drive_ref);
    }
}
