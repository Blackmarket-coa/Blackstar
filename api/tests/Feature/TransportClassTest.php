<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\TransportClass;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TransportClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_canonical_classes_idempotently(): void
    {
        $this->seed(TransportClassSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['air.drone', 'ground.bike', 'ground.truck', 'ground.van'],
            TransportClass::query()->get()->pluck('code')->sort()->values()->all()
        );

        $van = TransportClass::query()->where(['category' => 'ground', 'subtype' => 'van'])->sole();
        $this->assertNotNull($van->volume_limit);
    }

    public function test_seeder_does_not_overwrite_existing_rows(): void
    {
        TransportClass::factory()->create(['category' => 'ground', 'subtype' => 'van', 'weight_limit' => 1234]);

        $this->seed(TransportClassSeeder::class);

        $this->assertSame(4, TransportClass::query()->count());
        $this->assertEquals(1234, TransportClass::query()->where('subtype', 'van')->sole()->weight_limit);
    }

    public function test_authenticated_user_can_list_transport_classes(): void
    {
        $this->seed(TransportClassSeeder::class);
        $user = User::factory()->create(['node_id' => Node::factory()->create()->id]);

        $this->actingAs($user)
            ->getJson('/api/transport-classes')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.code', 'air.drone')
            ->assertJsonStructure([['id', 'code', 'category', 'subtype', 'weight_limit', 'volume_limit', 'range_limit', 'hazard_capability', 'regulatory_class', 'insurance_required_flag']]);
    }

    public function test_listing_transport_classes_requires_authentication(): void
    {
        $this->getJson('/api/transport-classes')->assertUnauthorized();
    }
}
