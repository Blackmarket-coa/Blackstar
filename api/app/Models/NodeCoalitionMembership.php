<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A node's membership of a Blackout coalition.
 *
 * `coalition_ref` is an opaque Blackout id: Blackstar compares it and never
 * parses it, so neither system's id space leaks into the other.
 */
class NodeCoalitionMembership extends Model
{
    use HasFactory;
    use HasUuids;

    public const ROLE_MEMBER = 'member';
    public const ROLE_COORDINATOR = 'coordinator';

    protected $fillable = [
        'node_id',
        'coalition_ref',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
