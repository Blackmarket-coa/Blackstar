<?php

namespace App\Services;

use App\Models\Node;
use App\Models\NodeTrustScore;

class NodeTrustScoringService
{
    public function recompute(Node $node, array $inputs): NodeTrustScore
    {
        $onTimeRate = $this->clampRate($inputs['on_time_rate'] ?? 0);
        $damageRate = $this->clampRate($inputs['damage_rate'] ?? 0);
        $disputeRate = $this->clampRate($inputs['dispute_rate'] ?? 0);
        $governanceParticipation = $this->clampRate($inputs['governance_participation'] ?? 0);

        $breakdown = $this->calculateBreakdown(
            $onTimeRate,
            $damageRate,
            $disputeRate,
            $governanceParticipation
        );

        return NodeTrustScore::updateOrCreate(
            ['node_id' => $node->id],
            [
                'on_time_rate' => $onTimeRate,
                'damage_rate' => $damageRate,
                'dispute_rate' => $disputeRate,
                'governance_participation' => $governanceParticipation,
                'on_time_component' => $breakdown['on_time_component'],
                'damage_component' => $breakdown['damage_component'],
                'dispute_component' => $breakdown['dispute_component'],
                'governance_component' => $breakdown['governance_component'],
                'aggregate_score' => $breakdown['aggregate_score'],
                'computed_at' => now(),
            ]
        );
    }

    public function calculateBreakdown(float $onTimeRate, float $damageRate, float $disputeRate, float $governanceParticipation): array
    {
        $onTimeComponent = round($onTimeRate * 50, 4);
        $damageComponent = round((1 - $damageRate) * 20, 4);
        $disputeComponent = round((1 - $disputeRate) * 20, 4);
        $governanceComponent = round($governanceParticipation * 10, 4);

        $aggregate = round($onTimeComponent + $damageComponent + $disputeComponent + $governanceComponent, 4);
        $aggregate = max(0, min(100, $aggregate));

        return [
            'on_time_component' => $onTimeComponent,
            'damage_component' => $damageComponent,
            'dispute_component' => $disputeComponent,
            'governance_component' => $governanceComponent,
            'aggregate_score' => $aggregate,
        ];
    }

    protected function clampRate(float $value): float
    {
        return max(0, min(1, round($value, 4)));
    }
}
