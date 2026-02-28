<?php

namespace Database\Factories;

use App\Models\ShipmentBoardListing;
use App\Models\ShipmentPaymentReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentPaymentReferenceFactory extends Factory
{
    protected $model = ShipmentPaymentReference::class;

    public function definition(): array
    {
        return [
            'shipment_board_listing_id' => ShipmentBoardListing::factory(),
            'buyer_vendor_payment_ref' => 'pay_' . $this->faker->bothify('????#####'),
            'vendor_node_settlement_ref' => 'set_' . $this->faker->bothify('????#####'),
            'platform_fee_ref' => 'fee_' . $this->faker->bothify('????#####'),
            'correlation_id' => 'corr_' . $this->faker->uuid(),
            'recorded_by_user_id' => User::factory(),
        ];
    }
}
