<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeTrustScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeTrustScoreDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_dashboard_exposes_aggregate_and_breakdown(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);
        NodeTrustScore::factory()->create(['node_id' => $node->id, 'aggregate_score' => 88.1234]);

        $this->actingAs($user)
            ->getJson('/api/vendor-dashboard/nodes/' . $node->id . '/trust-score')
            ->assertOk()
            ->assertJsonPath('node_id', $node->id)
            ->assertJsonPath('aggregate_score', 88.1234)
            ->assertJsonStructure([
                'aggregate_score',
                'breakdown' => [
                    'on_time_rate',
                    'damage_rate',
                    'dispute_rate',
                    'governance_participation',
                    'on_time_component',
                    'damage_component',
                    'dispute_component',
                    'governance_component',
                ],
            ]);
    }

    public function test_recompute_is_deterministic_and_node_scoped(): void
    {
        $node = Node::factory()->create();
        $otherNode = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        $payload = [
            'on_time_rate' => 0.91,
            'damage_rate' => 0.02,
            'dispute_rate' => 0.04,
            'governance_participation' => 0.7,
        ];

        $first = $this->actingAs($user)
            ->postJson('/api/vendor-dashboard/nodes/' . $node->id . '/trust-score/recompute', $payload)
            ->assertOk()
            ->json();

        $second = $this->actingAs($user)
            ->postJson('/api/vendor-dashboard/nodes/' . $node->id . '/trust-score/recompute', $payload)
            ->assertOk()
            ->json();

        $this->assertSame($first['aggregate_score'], $second['aggregate_score']);
        $this->assertSame($first['breakdown'], $second['breakdown']);

        $this->actingAs($user)
            ->postJson('/api/vendor-dashboard/nodes/' . $otherNode->id . '/trust-score/recompute', $payload)
            ->assertForbidden();
    }
}
