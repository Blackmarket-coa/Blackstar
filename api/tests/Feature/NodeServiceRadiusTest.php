<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use App\Services\ShipmentEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `nodes.service_radius` finally means something.
 *
 * It existed from the first migration, was required on node creation, and was
 * read by nothing — there was no coordinate on a node or a listing to measure
 * it from. Geography was `jurisdiction` compared as an exact string, so a node
 * in California was eligible for a listing in New York whenever both said "US".
 *
 * The fallback cases matter as much as the distance one: this must not make
 * every existing node ineligible for everything the day it ships.
 */
class NodeServiceRadiusTest extends TestCase
{
    use RefreshDatabase;

    private const SF = [37.7749, -122.4194];
    private const OAKLAND = [37.8044, -122.2712];
    private const NYC = [40.7128, -74.0060];

    private function eligibleNode(array $attributes = []): Node
    {
        $node = Node::factory()->create(array_merge([
            'jurisdiction' => 'US',
            'is_active' => true,
        ], $attributes));

        $class = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'van',
        ]);
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

    private function eligible(Node $node, ShipmentBoardListing $listing): bool
    {
        return app(ShipmentEligibilityService::class)
            ->isNodeEligibleForListing($node, $listing);
    }

    public function test_node_is_eligible_for_a_listing_inside_its_radius(): void
    {
        $node = $this->eligibleNode([
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 50,
        ]);
        $listing = $this->listing([
            'origin_latitude' => self::OAKLAND[0],
            'origin_longitude' => self::OAKLAND[1],
        ]);

        // Oakland is about 10 miles from San Francisco.
        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_node_is_not_eligible_for_a_listing_outside_its_radius(): void
    {
        // The case the platform could not previously express: same
        // jurisdiction, opposite coast.
        $node = $this->eligibleNode([
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 50,
        ]);
        $listing = $this->listing([
            'origin_latitude' => self::NYC[0],
            'origin_longitude' => self::NYC[1],
        ]);

        $this->assertFalse($this->eligible($node, $listing));
    }

    public function test_a_wide_enough_radius_still_reaches(): void
    {
        $node = $this->eligibleNode([
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 3000,
        ]);
        $listing = $this->listing([
            'origin_latitude' => self::NYC[0],
            'origin_longitude' => self::NYC[1],
        ]);

        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_a_node_without_coordinates_is_unaffected(): void
    {
        // Every node predating this change. If the radius applied to them the
        // board would empty on deploy.
        $node = $this->eligibleNode([
            'latitude' => null,
            'longitude' => null,
            'service_radius' => 5,
        ]);
        $listing = $this->listing([
            'origin_latitude' => self::NYC[0],
            'origin_longitude' => self::NYC[1],
        ]);

        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_a_listing_without_an_origin_is_unaffected(): void
    {
        $node = $this->eligibleNode([
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 5,
        ]);
        $listing = $this->listing([
            'origin_latitude' => null,
            'origin_longitude' => null,
        ]);

        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_zero_radius_means_unset_not_serves_nowhere(): void
    {
        // Zero is the column's default, so it is far more often "nobody filled
        // this in" than "this node serves nowhere".
        $node = $this->eligibleNode([
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 0,
        ]);
        $listing = $this->listing([
            'origin_latitude' => self::NYC[0],
            'origin_longitude' => self::NYC[1],
        ]);

        $this->assertTrue($this->eligible($node, $listing));
    }

    public function test_jurisdiction_still_decides_before_distance(): void
    {
        // Distance narrows eligibility; it does not widen it.
        $node = $this->eligibleNode([
            'jurisdiction' => 'CA',
            'latitude' => self::SF[0],
            'longitude' => self::SF[1],
            'service_radius' => 50,
        ]);
        $listing = $this->listing([
            'jurisdiction' => 'US',
            'origin_latitude' => self::OAKLAND[0],
            'origin_longitude' => self::OAKLAND[1],
        ]);

        $this->assertFalse($this->eligible($node, $listing));
    }

    public function test_coordinates_can_be_set_through_the_node_api(): void
    {
        // NodePolicy::create gates on the actor already belonging to a node —
        // node registration is node-scoped, not open to any authenticated user.
        // Every other Feature test builds its actor this way; these two were the
        // outliers and were asserting 201/422 against a 403 they never reached.
        $user = User::factory()->create(['node_id' => Node::factory()->create()->id]);

        $this->actingAs($user)
            ->postJson('/api/nodes', [
                'node_id' => 'node-geo-1',
                'legal_entity_name' => 'Geo Co',
                'jurisdiction' => 'US',
                'service_radius' => 25,
                'latitude' => self::SF[0],
                'longitude' => self::SF[1],
            ])
            ->assertCreated();

        $node = Node::where('node_id', 'node-geo-1')->firstOrFail();
        $this->assertEqualsWithDelta(self::SF[0], (float) $node->latitude, 0.0001);
        $this->assertEqualsWithDelta(self::SF[1], (float) $node->longitude, 0.0001);
    }

    public function test_the_node_api_rejects_impossible_coordinates(): void
    {
        $user = User::factory()->create(['node_id' => Node::factory()->create()->id]);

        $this->actingAs($user)
            ->postJson('/api/nodes', [
                'node_id' => 'node-geo-2',
                'legal_entity_name' => 'Geo Co',
                'jurisdiction' => 'US',
                'service_radius' => 25,
                'latitude' => 91,
                'longitude' => -122.4194,
            ])
            ->assertStatus(422);
    }
}
