<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-partner machine credential for the FreeBlackMarket bridge: the
 * signature header carries this credential's key_id, and verification uses
 * this credential's secret instead of the deployment-global one. Rotation is
 * overlap-based (issue a new credential, switch the sender, revoke the old) —
 * no flag day. `node_id` is null for marketplace peers (FBM itself) and set
 * for partner delivery nodes.
 */
class NodeCredential extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'node_id',
        'label',
        'key_id',
        'secret',
        'status',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
