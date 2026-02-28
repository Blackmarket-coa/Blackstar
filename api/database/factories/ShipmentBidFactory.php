<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\ShipmentBid;
use App\Models\ShipmentBoardListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentBidFactory extends Factory
{
    protected $model = ShipmentBid::class;

    public function definition(): array
    {
        return [
            'shipment_board_listing_id' => ShipmentBoardListing::factory(),
            'node_id' => Node::factory(),
            'amount' => 100,
            'currency' => 'USD',
            'note' => $this->faker->sentence(),
        ];
    }
}
