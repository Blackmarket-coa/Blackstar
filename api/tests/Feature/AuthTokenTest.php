<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase drops vendor migration paths, which is where Sanctum's
        // personal_access_tokens migration lives. Recreate it here in the
        // shape production ends up with once fleetbase/core-api's
        // fix_personal_access_tokens has run: uuid tokenable_id, matching
        // the uuid users.id.
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function operator(): User
    {
        $node = Node::factory()->create();

        // UserFactory's fixed hash is the string "password".
        return User::factory()->create(['email' => 'operator@example.com', 'node_id' => $node->id]);
    }

    public function test_valid_credentials_issue_a_bearer_token(): void
    {
        $user = $this->operator();

        $response = $this->postJson('/api/auth/token', [
            'email' => 'operator@example.com',
            'password' => 'password',
            'device_name' => 'dispatch-tablet',
        ])->assertOk()
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertIsString($response->json('token'));
        $this->assertSame(['token', 'token_type'], array_keys($response->json()));

        $stored = PersonalAccessToken::query()->sole();
        $this->assertSame('dispatch-tablet', $stored->name);
        $this->assertSame($user->id, $stored->tokenable_id);
    }

    public function test_device_name_is_optional(): void
    {
        $this->operator();

        $this->postJson('/api/auth/token', ['email' => 'operator@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_wrong_password_and_unknown_email_get_the_same_generic_422(): void
    {
        $this->operator();

        $wrongPassword = $this->postJson('/api/auth/token', ['email' => 'operator@example.com', 'password' => 'nope'])
            ->assertStatus(422);
        $unknownEmail = $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertStatus(422);

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_token_endpoint_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])
                ->assertStatus(422);
        }

        $this->postJson('/api/auth/token', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertStatus(429);
    }

    public function test_bearer_token_authenticates_protected_routes(): void
    {
        $user = $this->operator();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/nodes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $user->node_id);
    }

    public function test_protected_routes_reject_missing_and_bogus_tokens(): void
    {
        $this->getJson('/api/nodes')->assertUnauthorized();

        $this->withToken('1|not-a-real-token')->getJson('/api/nodes')->assertUnauthorized();
    }

    public function test_revoke_deletes_only_the_calling_token(): void
    {
        $user = $this->operator();
        $revoked = $user->createToken('phone')->plainTextToken;
        $kept = $user->createToken('laptop')->plainTextToken;

        $this->withToken($revoked)->postJson('/api/auth/token/revoke')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->assertSame(['laptop'], PersonalAccessToken::query()->pluck('name')->all());

        $this->withToken($revoked)->getJson('/api/nodes')->assertUnauthorized();
        $this->app['auth']->forgetGuards();

        $this->withToken($kept)->getJson('/api/nodes')->assertOk();
    }

    public function test_revoke_requires_authentication(): void
    {
        $this->postJson('/api/auth/token/revoke')->assertUnauthorized();
    }

    public function test_webhook_still_rejects_unsigned_requests(): void
    {
        config()->set('freeblackmarket.webhook_secret', 'test-webhook-secret');
        $user = $this->operator();

        // A bearer token is not a substitute for the HMAC signature.
        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/webhooks/freeblackmarket', ['event_id' => 'evt-1', 'event_type' => 'order.created', 'payload' => []])
            ->assertUnauthorized();
    }
}
