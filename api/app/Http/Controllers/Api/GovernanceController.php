<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernanceDecisionReference;
use App\Models\GovernanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public function settings(): JsonResponse
    {
        $settings = GovernanceSetting::query()->firstOrCreate([], ['federation_council_room_id' => null]);

        return response()->json($settings);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'federation_council_room_id' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = GovernanceSetting::query()->firstOrCreate([], ['federation_council_room_id' => null]);
        $settings->update($validated);

        return response()->json($settings->refresh());
    }

    public function appendOutcome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => ['nullable', 'uuid', 'exists:nodes,id'],
            'shipment_board_listing_id' => ['nullable', 'uuid', 'exists:shipment_board_listings,id'],
            'decision_ref' => ['required', 'string', 'max:255'],
            'decision_type' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
            'decided_at' => ['nullable', 'date'],
        ]);

        $authNodeId = auth()->user()?->node_id;
        $requestedNodeId = $validated['node_id'] ?? $authNodeId;

        if (!empty($requestedNodeId) && $authNodeId !== $requestedNodeId) {
            abort(403, 'Cannot append governance outcomes for another node.');
        }

        $existing = GovernanceDecisionReference::query()
            ->where('node_id', $requestedNodeId)
            ->where('decision_ref', $validated['decision_ref'])
            ->first();

        if ($existing) {
            return response()->json($existing, 200);
        }

        $outcome = GovernanceDecisionReference::create($validated + [
            'node_id' => $requestedNodeId,
            'recorded_by_user_id' => auth()->id(),
            'correlation_id' => $validated['correlation_id'] ?? request()->header('X-Correlation-ID'),
        ]);

        return response()->json($outcome, 201);
    }

    public function outcomes(Request $request): JsonResponse
    {
        $nodeId = auth()->user()?->node_id;

        $query = GovernanceDecisionReference::query()
            ->with(['node:id,node_id,governance_room_id', 'shipmentBoardListing:id,source_order_ref,status'])
            ->where(function ($q) use ($nodeId) {
                $q->whereNull('node_id');

                if ($nodeId) {
                    $q->orWhere('node_id', $nodeId);
                }
            });

        if ($request->filled('decision_type')) {
            $query->where('decision_type', $request->input('decision_type'));
        }

        if ($request->filled('decision_ref')) {
            $query->where('decision_ref', $request->input('decision_ref'));
        }

        return response()->json($query->latest('decided_at')->paginate(50));
    }

    public function showOutcome(GovernanceDecisionReference $governanceDecisionReference): JsonResponse
    {
        $nodeId = auth()->user()?->node_id;

        if ($governanceDecisionReference->node_id !== null && $governanceDecisionReference->node_id !== $nodeId) {
            abort(403, 'Forbidden');
        }

        return response()->json($governanceDecisionReference->load(['node:id,node_id,governance_room_id', 'shipmentBoardListing:id,source_order_ref,status']));
    }
}
