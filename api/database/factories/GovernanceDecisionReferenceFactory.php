<?php

namespace Database\Factories;

use App\Models\GovernanceDecisionReference;
use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GovernanceDecisionReferenceFactory extends Factory
{
    protected $model = GovernanceDecisionReference::class;

    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'shipment_board_listing_id' => ShipmentBoardListing::factory(),
            'decision_ref' => 'dec_' . $this->faker->bothify('??####'),
            'decision_type' => 'node_membership',
            'summary' => $this->faker->sentence(),
            'metadata' => ['source' => 'blackout'],
            'correlation_id' => 'corr_' . $this->faker->uuid(),
            'recorded_by_user_id' => User::factory(),
            'decided_at' => now(),
        ];
    }
}
