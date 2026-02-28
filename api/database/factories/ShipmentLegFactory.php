<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\ShipmentLeg;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentLegFactory extends Factory
{
    protected $model = ShipmentLeg::class;

    public function definition(): array
    {
        return [
            'shipment_board_listing_id' => ShipmentBoardListing::factory(),
            'sequence' => 1,
            'from_node_id' => Node::factory(),
            'to_node_id' => Node::factory(),
            'status' => ShipmentLeg::STATUS_PENDING,
            'proof_of_handoff_hash' => null,
            'settlement_ref' => null,
        ];
    }
}
