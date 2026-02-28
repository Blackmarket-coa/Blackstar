<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Services\NodeTrustScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeTrustScoreController extends Controller
{
    public function __construct(protected NodeTrustScoringService $scoring)
    {
    }

    public function show(Node $node): JsonResponse
    {
        $score = $node->trustScore;

        if (!$score) {
            $score = $this->scoring->recompute($node, [
                'on_time_rate' => 0,
                'damage_rate' => 0,
                'dispute_rate' => 0,
                'governance_participation' => 0,
            ]);
        }

        return response()->json([
            'node_id' => $node->id,
            'aggregate_score' => (float) $score->aggregate_score,
            'breakdown' => [
                'on_time_rate' => (float) $score->on_time_rate,
                'damage_rate' => (float) $score->damage_rate,
                'dispute_rate' => (float) $score->dispute_rate,
                'governance_participation' => (float) $score->governance_participation,
                'on_time_component' => (float) $score->on_time_component,
                'damage_component' => (float) $score->damage_component,
                'dispute_component' => (float) $score->dispute_component,
                'governance_component' => (float) $score->governance_component,
            ],
            'computed_at' => optional($score->computed_at)->toISOString(),
        ]);
    }

    public function recompute(Request $request, Node $node): JsonResponse
    {
        $authNodeId = auth()->user()?->node_id;
        if ($authNodeId !== $node->id) {
            abort(403, 'Cannot recompute trust score for another node.');
        }

        $validated = $request->validate([
            'on_time_rate' => ['required', 'numeric'],
            'damage_rate' => ['required', 'numeric'],
            'dispute_rate' => ['required', 'numeric'],
            'governance_participation' => ['required', 'numeric'],
        ]);

        $score = $this->scoring->recompute($node, $validated);

        return response()->json([
            'node_id' => $node->id,
            'aggregate_score' => (float) $score->aggregate_score,
            'breakdown' => [
                'on_time_rate' => (float) $score->on_time_rate,
                'damage_rate' => (float) $score->damage_rate,
                'dispute_rate' => (float) $score->dispute_rate,
                'governance_participation' => (float) $score->governance_participation,
                'on_time_component' => (float) $score->on_time_component,
                'damage_component' => (float) $score->damage_component,
                'dispute_component' => (float) $score->dispute_component,
                'governance_component' => (float) $score->governance_component,
            ],
            'computed_at' => optional($score->computed_at)->toISOString(),
        ]);
    }
}
