<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\ShipmentBoardListing;
use App\Models\TransportClass;
use App\Models\User;
use App\Services\ShipmentEligibilityService;
use Database\Seeders\TransportClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeTransportClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TransportClassSeeder::class);
    }

    protected function classId(string $code): string
    {
        return TransportClass::query()->get()->firstWhere('code', $code)->id;
    }

    public function test_operator_syncs_their_node_by_ids(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        $this->actingAs($user)
            ->putJson("/api/nodes/{$node->id}/transport-classes", [
                'transport_class_ids' => [$this->classId('ground.van'), $this->classId('air.drone')],
            ])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.code', 'air.drone')
            ->assertJsonPath('1.code', 'ground.van');

        $this->assertSame(2, $node->transportClasses()->count());
    }

    public function test_sync_by_codes_replaces_the_previous_set(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);
        $node->transportClasses()->attach($this->classId('ground.bike'));

        $this->actingAs($user)
            ->putJson("/api/nodes/{$node->id}/transport-classes", ['codes' => ['ground.van', 'ground.truck']])
            ->assertOk()
            ->assertJsonCount(2);

        $this->assertEqualsCanonicalizing(
            ['ground.truck', 'ground.van'],
            $node->transportClasses()->get()->pluck('code')->all()
        );
    }

    public function test_ids_and_codes_are_combined_and_empty_list_clears(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        $this->actingAs($user)
            ->putJson("/api/nodes/{$node->id}/transport-classes", [
                'transport_class_ids' => [$this->classId('ground.van')],
                'codes' => ['ground.van', 'air.drone'],
            ])
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($user)
            ->putJson("/api/nodes/{$node->id}/transport-classes", ['codes' => []])
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_unknown_ids_codes_and_missing_payload_are_rejected(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);
        $url = "/api/nodes/{$node->id}/transport-classes";

        $this->actingAs($user)->putJson($url, ['codes' => ['sea.submarine']])
            ->assertStatus(422)->assertJsonValidationErrors('codes');
        $this->actingAs($user)->putJson($url, ['transport_class_ids' => ['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d']])
            ->assertStatus(422)->assertJsonValidationErrors('transport_class_ids.0');
        $this->actingAs($user)->putJson($url, [])
            ->assertStatus(422)->assertJsonValidationErrors('transport_class_ids');

        $this->assertSame(0, $node->transportClasses()->count());
    }

    public function test_another_nodes_operator_is_forbidden(): void
    {
        $node = Node::factory()->create();
        $other = User::factory()->create(['node_id' => Node::factory()->create()->id]);

        $this->actingAs($other)
            ->putJson("/api/nodes/{$node->id}/transport-classes", ['codes' => ['ground.van']])
            ->assertForbidden();

        $this->assertSame(0, $node->transportClasses()->count());
    }

    public function test_requires_authentication(): void
    {
        $node = Node::factory()->create();

        $this->putJson("/api/nodes/{$node->id}/transport-classes", ['codes' => ['ground.van']])
            ->assertUnauthorized();
    }

    public function test_declared_class_makes_an_attested_node_eligible(): void
    {
        $node = Node::factory()->create([
            'jurisdiction' => 'US',
            'is_active' => true,
            'transport_law_attestation_hash' => 'h1',
            'license_attestation_hash' => 'h2',
            'insurance_attestation_hash' => 'h3',
            'platform_indemnification_attestation_hash' => 'h4',
        ]);
        $user = User::factory()->create(['node_id' => $node->id]);
        // Factory listing: ground/van, weight 100, volume 10, range 50, STD.
        $listing = ShipmentBoardListing::factory()->create(['jurisdiction' => 'US']);
        $eligibility = app(ShipmentEligibilityService::class);

        $this->assertFalse($eligibility->isNodeEligibleForListing($node, $listing));

        $this->actingAs($user)
            ->putJson("/api/nodes/{$node->id}/transport-classes", ['codes' => ['ground.van']])
            ->assertOk();

        $this->assertTrue($eligibility->isNodeEligibleForListing($node->refresh(), $listing));
    }
}
