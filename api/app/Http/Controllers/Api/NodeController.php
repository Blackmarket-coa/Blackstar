<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Node::class);

        return response()->json(Node::query()->whereKey(auth()->user()->node_id)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Node::class);

        $node = Node::create($request->validate([
            'node_id' => ['required', 'string', 'max:255', 'unique:nodes,node_id'],
            'legal_entity_name' => ['required', 'string', 'max:255'],
            'jurisdiction' => ['required', 'string', 'max:255'],
            'service_radius' => ['required', 'numeric'],
            'contact' => ['nullable', 'array'],
            'insurance_attestation_hash' => ['nullable', 'string', 'max:255'],
            'license_attestation_hash' => ['nullable', 'string', 'max:255'],
            'transport_capabilities' => ['nullable', 'array'],
            'governance_room_id' => ['nullable', 'string', 'max:255'],
            'reputation_score' => ['nullable', 'numeric'],
        ]));

        return response()->json($node, 201);
    }

    public function show(Node $node): JsonResponse
    {
        $this->authorize('view', $node);

        return response()->json($node);
    }

    public function update(Request $request, Node $node): JsonResponse
    {
        $this->authorize('update', $node);

        $node->update($request->validate([
            'legal_entity_name' => ['sometimes', 'string', 'max:255'],
            'jurisdiction' => ['sometimes', 'string', 'max:255'],
            'service_radius' => ['sometimes', 'numeric'],
            'contact' => ['nullable', 'array'],
            'insurance_attestation_hash' => ['nullable', 'string', 'max:255'],
            'license_attestation_hash' => ['nullable', 'string', 'max:255'],
            'transport_capabilities' => ['nullable', 'array'],
            'governance_room_id' => ['nullable', 'string', 'max:255'],
            'reputation_score' => ['nullable', 'numeric'],
        ]));

        return response()->json($node->refresh());
    }

    public function destroy(Node $node): JsonResponse
    {
        $this->authorize('delete', $node);
        $node->delete();

        return response()->json(status: 204);
    }
}
