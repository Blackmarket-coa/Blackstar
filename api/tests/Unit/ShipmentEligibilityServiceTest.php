<?php

namespace Tests\Unit;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Services\ShipmentEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_when_transport_class_satisfies_all_constraints(): void
    {
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create([
            'category' => 'ground',
            'subtype' => 'van',
            'weight_limit' => 1500,
            'range_limit' => 500,
            'hazard_capability' => true,
            'regulatory_class' => 'HZ-A',
            'insurance_required_flag' => true,
        ]);
        $node->transportClasses()->attach($transportClass->id);

        $listing = ShipmentBoardListing::factory()->make([
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 800,
            'required_range_limit' => 200,
            'requires_hazard_capability' => true,
            'required_regulatory_class' => 'HZ-A',
            'insurance_required_flag' => true,
        ]);

        $eligible = app(ShipmentEligibilityService::class)->isNodeEligibleForListing($node, $listing);

        $this->assertTrue($eligible);
    }

    public function test_does_not_match_when_constraints_fail(): void
    {
        $node = Node::factory()->create(['jurisdiction' => 'US']);
        $transportClass = TransportClass::factory()->create([
            'category' => 'air',
            'subtype' => 'drone',
            'weight_limit' => 8,
            'range_limit' => 25,
            'hazard_capability' => false,
            'regulatory_class' => 'STD',
            'insurance_required_flag' => false,
        ]);
        $node->transportClasses()->attach($transportClass->id);

        $listing = ShipmentBoardListing::factory()->make([
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'truck',
            'required_weight_limit' => 500,
            'required_range_limit' => 100,
            'requires_hazard_capability' => true,
            'required_regulatory_class' => 'HZ-A',
            'insurance_required_flag' => true,
        ]);

        $eligible = app(ShipmentEligibilityService::class)->isNodeEligibleForListing($node, $listing);

        $this->assertFalse($eligible);
    }
}
