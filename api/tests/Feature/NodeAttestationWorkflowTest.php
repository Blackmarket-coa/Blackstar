<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeAttestationAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeAttestationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_node_can_be_activated_after_required_attestations_are_accepted(): void
    {
        $node = Node::factory()->create([
            'is_active' => false,
            'activated_at' => null,
            'transport_law_attestation_hash' => null,
            'insurance_attestation_hash' => null,
            'license_attestation_hash' => null,
            'platform_indemnification_attestation_hash' => null,
        ]);
        $user = User::factory()->create(['node_id' => $node->id]);

        $payload = [
            'transport_law_compliance' => [
                'accepted' => true,
                'signed_hash_artifact' => 'hash-transport-law-v1',
            ],
            'license_and_insurance_responsibility' => [
                'accepted' => true,
                'license_attestation_hash' => 'hash-license-v1',
                'insurance_attestation_hash' => 'hash-insurance-v1',
            ],
            'platform_indemnification' => [
                'accepted' => true,
                'signed_hash_artifact' => 'hash-indemnification-v1',
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/nodes/' . $node->id . '/attest', $payload)
            ->assertOk()
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('transport_law_attestation_hash', 'hash-transport-law-v1')
            ->assertJsonPath('license_attestation_hash', 'hash-license-v1')
            ->assertJsonPath('insurance_attestation_hash', 'hash-insurance-v1')
            ->assertJsonPath('platform_indemnification_attestation_hash', 'hash-indemnification-v1');

        $this->assertDatabaseCount('node_attestation_acceptances', 3);
        $this->assertDatabaseHas('node_attestation_acceptances', [
            'node_id' => $node->id,
            'term_key' => NodeAttestationAcceptance::TERM_TRANSPORT_LAW,
            'signed_hash_artifact' => 'hash-transport-law-v1',
        ]);
        $this->assertDatabaseHas('node_attestation_acceptances', [
            'node_id' => $node->id,
            'term_key' => NodeAttestationAcceptance::TERM_PLATFORM_INDEMNIFICATION,
            'signed_hash_artifact' => 'hash-indemnification-v1',
        ]);
    }

    public function test_activation_requires_all_required_acceptances(): void
    {
        $node = Node::factory()->create(['is_active' => false, 'activated_at' => null]);
        $user = User::factory()->create(['node_id' => $node->id]);

        $this->actingAs($user)
            ->postJson('/api/nodes/' . $node->id . '/attest', [
                'transport_law_compliance' => [
                    'accepted' => true,
                    'signed_hash_artifact' => 'hash-transport-law-v1',
                ],
                'license_and_insurance_responsibility' => [
                    'accepted' => false,
                    'license_attestation_hash' => 'hash-license-v1',
                    'insurance_attestation_hash' => 'hash-insurance-v1',
                ],
                'platform_indemnification' => [
                    'accepted' => true,
                    'signed_hash_artifact' => 'hash-indemnification-v1',
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('node_attestation_acceptances', 0);
        $this->assertFalse($node->refresh()->is_active);
    }
}
