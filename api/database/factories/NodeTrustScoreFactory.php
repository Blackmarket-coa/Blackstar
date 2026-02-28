<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\NodeTrustScore;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeTrustScoreFactory extends Factory
{
    protected $model = NodeTrustScore::class;

    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'on_time_rate' => 0.9,
            'damage_rate' => 0.02,
            'dispute_rate' => 0.03,
            'governance_participation' => 0.8,
            'on_time_component' => 45,
            'damage_component' => 19.6,
            'dispute_component' => 19.4,
            'governance_component' => 8,
            'aggregate_score' => 92,
            'computed_at' => now(),
        ];
    }
}
