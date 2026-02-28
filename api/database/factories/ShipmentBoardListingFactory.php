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
            'jurisdiction' => 'US',
            'required_category' => 'ground',
            'required_subtype' => 'van',
            'required_weight_limit' => 100,
            'required_range_limit' => 50,
            'requires_hazard_capability' => false,
            'required_regulatory_class' => 'STD',
            'insurance_required_flag' => false,
            'required_transport_capabilities' => ['van'],
            'created_by_user_id' => User::factory(),
        ];
    }
}
