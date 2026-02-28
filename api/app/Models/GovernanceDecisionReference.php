<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceDecisionReference extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'node_id',
        'shipment_board_listing_id',
        'decision_ref',
        'decision_type',
        'summary',
        'metadata',
        'correlation_id',
        'recorded_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function shipmentBoardListing(): BelongsTo
    {
        return $this->belongsTo(ShipmentBoardListing::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
