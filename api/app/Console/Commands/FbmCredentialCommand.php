<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Models\NodeCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Lifecycle for per-partner bridge credentials. The secret is printed exactly
 * once at issue/rotate time and stored encrypted; there is no way to read it
 * back later — re-issue instead. Rotation is overlap-based: the new
 * credential is active immediately, the old one keeps verifying until it is
 * explicitly revoked, so the sender switches on its own schedule.
 */
class FbmCredentialCommand extends Command
{
    protected $signature = 'fbm:credential
        {action : issue | rotate | revoke | list}
        {--label= : Human-readable label (issue)}
        {--node= : Node uuid this credential belongs to; omit for a marketplace peer (issue)}
        {--key-id= : Existing credential key id (rotate / revoke)}';

    protected $description = 'Issue, rotate, revoke, or list per-partner FreeBlackMarket bridge credentials';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'issue' => $this->issue(),
            'rotate' => $this->rotate(),
            'revoke' => $this->revoke(),
            'list' => $this->listCredentials(),
            default => $this->invalidAction(),
        };
    }

    protected function issue(?string $label = null, ?string $nodeId = null): int
    {
        $label = $label ?? (string) $this->option('label');
        if ($label === '') {
            $this->error('A --label is required to issue a credential.');

            return self::FAILURE;
        }

        $nodeId = $nodeId ?? ($this->option('node') ?: null);
        if ($nodeId !== null && Node::query()->find($nodeId) === null) {
            $this->error("No node with id [{$nodeId}].");

            return self::FAILURE;
        }

        $credential = NodeCredential::create([
            'node_id' => $nodeId,
            'label' => $label,
            'key_id' => 'bsk_' . Str::lower(Str::random(20)),
            'secret' => bin2hex(random_bytes(32)),
            'status' => NodeCredential::STATUS_ACTIVE,
        ]);

        $this->info('Credential issued. The secret is shown ONCE — store it now.');
        $this->line('  key_id: ' . $credential->key_id);
        $this->line('  secret: ' . $credential->secret);

        return self::SUCCESS;
    }

    protected function rotate(): int
    {
        $old = $this->findByKeyIdOption();
        if ($old === null) {
            return self::FAILURE;
        }

        $this->info("Rotating [{$old->key_id}] — it stays active until you revoke it.");

        return $this->issue($old->label, $old->node_id);
    }

    protected function revoke(): int
    {
        $credential = $this->findByKeyIdOption();
        if ($credential === null) {
            return self::FAILURE;
        }

        $credential->forceFill([
            'status' => NodeCredential::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        $this->info("Revoked [{$credential->key_id}].");

        return self::SUCCESS;
    }

    protected function listCredentials(): int
    {
        $rows = NodeCredential::query()
            ->orderBy('created_at')
            ->get()
            ->map(fn (NodeCredential $c) => [
                $c->key_id,
                $c->label,
                $c->node_id ?? '(marketplace peer)',
                $c->status,
                optional($c->last_used_at)->toIso8601String() ?? 'never',
            ]);

        $this->table(['key_id', 'label', 'node', 'status', 'last_used_at'], $rows->all());

        return self::SUCCESS;
    }

    protected function findByKeyIdOption(): ?NodeCredential
    {
        $keyId = (string) $this->option('key-id');
        if ($keyId === '') {
            $this->error('--key-id is required for this action.');

            return null;
        }

        $credential = NodeCredential::query()->where('key_id', $keyId)->first();
        if ($credential === null) {
            $this->error("No credential with key id [{$keyId}].");
        }

        return $credential;
    }

    protected function invalidAction(): int
    {
        $this->error('Action must be one of: issue, rotate, revoke, list.');

        return self::FAILURE;
    }
}
