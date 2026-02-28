<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Fleet;
use App\Models\Node;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NodeTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_node_crud_endpoints_work_for_authenticated_user(): void
    {
        $node = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        $this->actingAs($user)
            ->getJson('/api/nodes')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($user)
            ->putJson('/api/nodes/' . $node->id, ['legal_entity_name' => 'Updated Legal Entity'])
            ->assertOk()
            ->assertJsonPath('legal_entity_name', 'Updated Legal Entity');

        $created = $this->actingAs($user)
            ->postJson('/api/nodes', [
                'node_id' => 'NODE-CREATE1',
                'legal_entity_name' => 'Create Entity',
                'jurisdiction' => 'US',
                'service_radius' => 55,
                'contact' => ['email' => 'ops@example.com'],
                'transport_capabilities' => ['truck'],
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($user)
            ->deleteJson('/api/nodes/' . $created['id'])
            ->assertNoContent();
    }

    public function test_fleet_vehicle_driver_crud_autoscopes_to_authenticated_users_node(): void
    {
        $node = Node::factory()->create();
        $otherNode = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        Fleet::factory()->create(['node_id' => $node->id, 'name' => 'My Fleet']);
        Fleet::factory()->create(['node_id' => $otherNode->id, 'name' => 'Other Fleet']);

        $this->actingAs($user)
            ->getJson('/api/fleets')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'My Fleet'])
            ->assertJsonMissing(['name' => 'Other Fleet']);

        $fleetResponse = $this->actingAs($user)
            ->postJson('/api/fleets', ['name' => 'Created Fleet'])
            ->assertCreated()
            ->json();

        $fleet = Fleet::query()->find($fleetResponse['id']);
        $this->assertSame($node->id, $fleet->node_id);

        $vehicleResponse = $this->actingAs($user)
            ->postJson('/api/vehicles', [
                'name' => 'Truck 1',
                'plate_number' => 'AB-1234',
                'fleet_id' => $fleet->id,
            ])
            ->assertCreated()
            ->json();

        $driverResponse = $this->actingAs($user)
            ->postJson('/api/drivers', [
                'name' => 'Jane Driver',
                'email' => 'jane@example.com',
                'fleet_id' => $fleet->id,
            ])
            ->assertCreated()
            ->json();

        $this->assertSame($node->id, Vehicle::query()->find($vehicleResponse['id'])->node_id);
        $this->assertSame($node->id, Driver::query()->find($driverResponse['id'])->node_id);
    }

    public function test_cross_node_access_is_forbidden_by_policy(): void
    {
        $node = Node::factory()->create();
        $otherNode = Node::factory()->create();
        $user = User::factory()->create(['node_id' => $node->id]);

        $foreignFleet = Fleet::withoutGlobalScope('node_tenancy')->create([
            'node_id' => $otherNode->id,
            'name' => 'Foreign Fleet',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/fleets/' . $foreignFleet->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson('/api/fleets/' . $foreignFleet->id, ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson('/api/fleets/' . $foreignFleet->id)
            ->assertForbidden();
    }
}
