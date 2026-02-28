<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeTrustScore extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'node_id',
        'on_time_rate',
        'damage_rate',
        'dispute_rate',
        'governance_participation',
        'on_time_component',
        'damage_component',
        'dispute_component',
        'governance_component',
        'aggregate_score',
        'computed_at',
    ];

    protected $casts = [
        'on_time_rate' => 'decimal:4',
        'damage_rate' => 'decimal:4',
        'dispute_rate' => 'decimal:4',
        'governance_participation' => 'decimal:4',
        'on_time_component' => 'decimal:4',
        'damage_component' => 'decimal:4',
        'dispute_component' => 'decimal:4',
        'governance_component' => 'decimal:4',
        'aggregate_score' => 'decimal:4',
        'computed_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
