<?php

namespace Database\Factories;

use App\Models\ShipmentBoardListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentBoardListingFactory extends Factory
{
    protected $model = ShipmentBoardListing::class;

    public function definition(): array
    {
        return [
            'source_order_ref' => 'ORD-' . $this->faker->unique()->numerify('#####'),
            'status' => ShipmentBoardListing::STATUS_OPEN,
            'claim_policy' => 'first_claim',
            'job_type' => 'delivery',
            'bounty_amount' => 125,
            'bounty_currency' => 'USD',
            'origin' => 'Warehouse A',
            'destination' => 'Dropoff B',
            'work_order' => 'Deliver package and capture recipient signature.',
            'creator_qa_checklist' => ['label scanned', 'photo at handoff'],
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_volume_limit' => 10,
            'required_range_limit' => 50,
            'requires_hazard_capability' => false,
            'required_regulatory_class' => 'STD',
            'insurance_required_flag' => false,
            'required_transport_capabilities' => ['van'],
            'created_by_user_id' => User::factory(),
        ];
    }
}
