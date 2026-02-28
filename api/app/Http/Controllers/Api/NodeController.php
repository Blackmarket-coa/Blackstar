<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeAttestationAcceptance;
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
            'transport_law_attestation_hash' => ['nullable', 'string', 'max:255'],
            'platform_indemnification_attestation_hash' => ['nullable', 'string', 'max:255'],
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
            'transport_law_attestation_hash' => ['nullable', 'string', 'max:255'],
            'platform_indemnification_attestation_hash' => ['nullable', 'string', 'max:255'],
            'transport_capabilities' => ['nullable', 'array'],
            'governance_room_id' => ['nullable', 'string', 'max:255'],
            'reputation_score' => ['nullable', 'numeric'],
        ]));

        return response()->json($node->refresh());
    }

    public function attest(Request $request, Node $node): JsonResponse
    {
        $this->authorize('update', $node);

        $data = $request->validate([
            'transport_law_compliance.accepted' => ['required', 'accepted'],
            'transport_law_compliance.signed_hash_artifact' => ['required', 'string', 'max:255'],
            'license_and_insurance_responsibility.accepted' => ['required', 'accepted'],
            'license_and_insurance_responsibility.license_attestation_hash' => ['required', 'string', 'max:255'],
            'license_and_insurance_responsibility.insurance_attestation_hash' => ['required', 'string', 'max:255'],
            'platform_indemnification.accepted' => ['required', 'accepted'],
            'platform_indemnification.signed_hash_artifact' => ['required', 'string', 'max:255'],
        ]);

        $acceptedAt = now();

        $node->attestationAcceptances()->createMany([
            [
                'accepted_by_user_id' => auth()->id(),
                'term_key' => NodeAttestationAcceptance::TERM_TRANSPORT_LAW,
                'signed_hash_artifact' => $data['transport_law_compliance']['signed_hash_artifact'],
                'accepted_at' => $acceptedAt,
            ],
            [
                'accepted_by_user_id' => auth()->id(),
                'term_key' => NodeAttestationAcceptance::TERM_LICENSE_AND_INSURANCE,
                'signed_hash_artifact' => hash('sha256', $data['license_and_insurance_responsibility']['license_attestation_hash'] . '|' . $data['license_and_insurance_responsibility']['insurance_attestation_hash']),
                'accepted_at' => $acceptedAt,
            ],
            [
                'accepted_by_user_id' => auth()->id(),
                'term_key' => NodeAttestationAcceptance::TERM_PLATFORM_INDEMNIFICATION,
                'signed_hash_artifact' => $data['platform_indemnification']['signed_hash_artifact'],
                'accepted_at' => $acceptedAt,
            ],
        ]);

        $node->update([
            'transport_law_attestation_hash' => $data['transport_law_compliance']['signed_hash_artifact'],
            'license_attestation_hash' => $data['license_and_insurance_responsibility']['license_attestation_hash'],
            'insurance_attestation_hash' => $data['license_and_insurance_responsibility']['insurance_attestation_hash'],
            'platform_indemnification_attestation_hash' => $data['platform_indemnification']['signed_hash_artifact'],
            'is_active' => true,
            'activated_at' => $acceptedAt,
        ]);

        return response()->json($node->refresh()->load('attestationAcceptances'));
    }

    public function destroy(Node $node): JsonResponse
    {
        $this->authorize('delete', $node);
        $node->delete();

        return response()->json(status: 204);
    }
}
