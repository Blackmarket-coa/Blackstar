<?php

namespace Tests\Feature;

use App\Models\GovernanceDecisionReference;
use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceOutcomeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function createNodeUser(string $jurisdiction = 'US'): array
    {
        $node = Node::factory()->create(['jurisdiction' => $jurisdiction]);
        // A transport class is a shared taxonomy row (unique on
        // category+subtype) — reuse it across nodes instead of colliding.
        $tc = TransportClass::query()->firstOrCreate(
            ['category' => 'ground', 'subtype' => 'van'],
            TransportClass::factory()->raw(['category' => 'ground', 'subtype' => 'van'])
        );
        $node->transportClasses()->attach($tc->id);
        $user = User::factory()->create(['node_id' => $node->id]);

        return [$node, $user];
    }

    public function test_can_append_and_query_governance_outcomes_for_own_node(): void
    {
        [$node, $user] = $this->createNodeUser();
        $creator = User::factory()->create();
        $listing = ShipmentBoardListing::factory()->create(['created_by_user_id' => $creator->id]);

        $this->actingAs($user)
            ->postJson('/api/governance/outcomes', [
                'shipment_board_listing_id' => $listing->id,
                'decision_ref' => 'blackout#123',
                'decision_type' => 'relay_standard',
                'summary' => 'Approved relay policy update.',
                'metadata' => ['hash' => 'abc123'],
            ], ['X-Correlation-ID' => 'corr-gov-1'])
            ->assertCreated()
            ->assertJsonPath('node_id', $node->id)
            ->assertJsonPath('decision_ref', 'blackout#123')
            ->assertJsonPath('correlation_id', 'corr-gov-1');

        $this->actingAs($user)
            ->getJson('/api/governance/outcomes?decision_type=relay_standard')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_access_control_blocks_cross_node_governance_reads_and_writes(): void
    {
        [$nodeA, $userA] = $this->createNodeUser('US');
        [$nodeB, $userB] = $this->createNodeUser('CA');

        $foreignOutcome = GovernanceDecisionReference::factory()->create([
            'node_id' => $nodeB->id,
            'decision_ref' => 'blackout#foreign',
        ]);

        $this->actingAs($userA)
            ->getJson('/api/governance/outcomes/' . $foreignOutcome->id)
            ->assertForbidden();

        $this->actingAs($userA)
            ->postJson('/api/governance/outcomes', [
                'node_id' => $nodeB->id,
                'decision_ref' => 'blackout#cross-write',
                'decision_type' => 'node_membership',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('governance_decision_references', ['decision_ref' => 'blackout#cross-write']);
    }


    public function test_in_core_governance_execution_endpoints_are_forbidden_or_absent(): void
    {
        [, $user] = $this->createNodeUser();

        // 404 (no route) and 405 (URI exists for another verb, execution verb
        // absent) both satisfy "forbidden or absent" — what must not exist is
        // a POST execution endpoint.
        $this->assertContains(
            $this->actingAs($user)
                ->postJson('/api/governance/proposals', ['title' => 'Should not exist'])
                ->getStatusCode(),
            [404, 405],
            'Governance proposal execution endpoint must not exist in core.'
        );

        $this->assertContains(
            $this->actingAs($user)
                ->postJson('/api/governance/votes', ['proposal_id' => 'p-1', 'vote' => 'yes'])
                ->getStatusCode(),
            [404, 405],
            'Governance vote execution endpoint must not exist in core.'
        );

        $this->assertContains(
            $this->actingAs($user)
                ->postJson('/api/governance/outcomes/execute', ['decision_ref' => 'blackout#123'])
                ->getStatusCode(),
            [404, 405],
            'Governance outcome execution endpoint must not exist in core.'
        );
    }

    public function test_governance_decisions_are_immutable_once_logged(): void
    {
        [$node, $user] = $this->createNodeUser();

        $outcome = GovernanceDecisionReference::factory()->create([
            'node_id' => $node->id,
            'decision_ref' => 'blackout#immutable',
            'decision_type' => 'cargo_restriction',
            'summary' => 'Original summary',
        ]);

        $outcome->summary = 'Tampered summary';
        $saved = $outcome->save();

        $this->assertFalse($saved);
        $this->assertSame('Original summary', $outcome->fresh()->summary);

        $deleted = $outcome->delete();

        $this->assertFalse($deleted);
        $this->assertDatabaseHas('governance_decision_references', ['id' => $outcome->id]);
    }
}
