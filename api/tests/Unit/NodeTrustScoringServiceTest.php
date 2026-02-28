<?php

namespace Tests\Unit;

use App\Models\Node;
use App\Services\NodeTrustScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeTrustScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_expected_breakdown_deterministically(): void
    {
        $service = app(NodeTrustScoringService::class);

        $a = $service->calculateBreakdown(0.95, 0.01, 0.03, 0.80);
        $b = $service->calculateBreakdown(0.95, 0.01, 0.03, 0.80);

        $this->assertSame($a, $b);
        $this->assertSame(47.5, $a['on_time_component']);
        $this->assertSame(19.8, $a['damage_component']);
        $this->assertSame(19.4, $a['dispute_component']);
        $this->assertSame(8.0, $a['governance_component']);
        $this->assertSame(94.7, $a['aggregate_score']);
    }

    public function test_clamps_edge_case_inputs_to_valid_range(): void
    {
        $service = app(NodeTrustScoringService::class);
        $node = Node::factory()->create();

        $score = $service->recompute($node, [
            'on_time_rate' => 2.5,
            'damage_rate' => -1,
            'dispute_rate' => 8,
            'governance_participation' => -4,
        ]);

        $this->assertSame('1.0000', $score->on_time_rate);
        $this->assertSame('0.0000', $score->damage_rate);
        $this->assertSame('1.0000', $score->dispute_rate);
        $this->assertSame('0.0000', $score->governance_participation);
        $this->assertSame('70.0000', $score->aggregate_score);
    }
}
