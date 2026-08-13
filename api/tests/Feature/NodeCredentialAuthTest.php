<?php

namespace Tests\Feature;

use App\Models\NodeCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NodeCredentialAuthTest extends TestCase
{
    use RefreshDatabase;

    private const GLOBAL_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('freeblackmarket.webhook_secret', self::GLOBAL_SECRET);
    }

    protected function payload(string $eventId): array
    {
        return ['event_id' => $eventId, 'event_type' => 'order.created', 'payload' => []];
    }

    protected function signedHeaders(array $payload, string $secret, ?string $keyId = null): array
    {
        $ts = (string) now()->getTimestamp();
        $headers = [
            'X-FBM-Timestamp' => $ts,
            'X-FBM-Signature' => hash_hmac('sha256', $ts . '.' . json_encode($payload), $secret),
        ];
        if ($keyId !== null) {
            $headers['X-FBM-Key-ID'] = $keyId;
        }

        return $headers;
    }

    protected function makeCredential(string $secret, string $status = NodeCredential::STATUS_ACTIVE): NodeCredential
    {
        return NodeCredential::create([
            'node_id' => null,
            'label' => 'test peer',
            'key_id' => 'bsk_' . bin2hex(random_bytes(8)),
            'secret' => $secret,
            'status' => $status,
        ]);
    }

    public function test_key_id_credential_authenticates_the_webhook(): void
    {
        $credential = $this->makeCredential('per-partner-secret-1');

        $payload = $this->payload('evt-cred-ok');
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, 'per-partner-secret-1', $credential->key_id))
            ->assertStatus(202)
            ->assertJsonPath('status', 'processed');

        $this->assertNotNull($credential->fresh()->last_used_at);
    }

    public function test_revoked_credential_is_rejected(): void
    {
        $credential = $this->makeCredential('per-partner-secret-2', NodeCredential::STATUS_REVOKED);

        $payload = $this->payload('evt-cred-revoked');
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, 'per-partner-secret-2', $credential->key_id))
            ->assertStatus(401);

        $this->assertDatabaseCount('fbm_inbound_event_receipts', 0);
    }

    public function test_unknown_key_id_answers_like_a_bad_signature(): void
    {
        $payload = $this->payload('evt-cred-unknown');
        $response = $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, self::GLOBAL_SECRET, 'bsk_does_not_exist'));

        $response->assertStatus(401);
        $this->assertSame('Invalid signature.', $response->json('message'));
    }

    public function test_global_secret_still_verifies_without_a_key_id_while_migrating(): void
    {
        $payload = $this->payload('evt-global-fallback');
        $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, self::GLOBAL_SECRET))
            ->assertStatus(202);
    }

    public function test_require_key_id_retires_the_global_secret(): void
    {
        config()->set('freeblackmarket.require_key_id', true);

        // Correctly signed with the global secret — but the deployment now
        // requires per-partner credentials, so it must not authenticate.
        $payload = $this->payload('evt-global-retired');
        $response = $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, self::GLOBAL_SECRET));

        $response->assertStatus(401);
        $this->assertSame('Key ID required.', $response->json('message'));
        $this->assertDatabaseCount('fbm_inbound_event_receipts', 0);
    }

    public function test_rotation_overlap_keeps_the_old_credential_verifying(): void
    {
        $this->artisan('fbm:credential', ['action' => 'issue', '--label' => 'FBM production'])
            ->assertSuccessful();

        $old = NodeCredential::query()->firstOrFail();

        $this->artisan('fbm:credential', ['action' => 'rotate', '--key-id' => $old->key_id])
            ->assertSuccessful();

        $this->assertSame(2, NodeCredential::query()->active()->count());
        $this->assertSame(NodeCredential::STATUS_ACTIVE, $old->fresh()->status);

        // Both generations verify: the sender switches on its own schedule.
        foreach (NodeCredential::query()->active()->get() as $i => $credential) {
            $payload = $this->payload('evt-rotation-' . $i);
            $this->postJson('/api/webhooks/freeblackmarket', $payload, $this->signedHeaders($payload, $credential->secret, $credential->key_id))
                ->assertStatus(202);
        }

        $this->artisan('fbm:credential', ['action' => 'revoke', '--key-id' => $old->key_id])
            ->assertSuccessful();
        $this->assertSame(NodeCredential::STATUS_REVOKED, $old->fresh()->status);
    }

    public function test_secrets_are_encrypted_at_rest(): void
    {
        $credential = $this->makeCredential('plaintext-secret-value');

        $raw = DB::table('node_credentials')->where('id', $credential->id)->value('secret');

        $this->assertNotSame('plaintext-secret-value', $raw);
        $this->assertSame('plaintext-secret-value', $credential->fresh()->secret);
    }
}
