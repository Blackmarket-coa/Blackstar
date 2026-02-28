<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ShipmentBoardListing extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'source_order_ref',
        'status',
        'claim_policy',
        'jurisdiction',
        'required_category',
        'required_subtype',
        'required_weight_limit',
        'required_range_limit',
        'requires_hazard_capability',
        'required_regulatory_class',
        'insurance_required_flag',
        'required_transport_capabilities',
        'created_by_user_id',
        'claimed_by_node_id',
        'claimed_at',
        'in_transit_at',
        'delivered_at',
        'disputed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'required_transport_capabilities' => 'array',
        'required_weight_limit' => 'decimal:2',
        'required_range_limit' => 'decimal:2',
        'requires_hazard_capability' => 'boolean',
        'insurance_required_flag' => 'boolean',
        'claimed_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
        'disputed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function claimedByNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'claimed_by_node_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(ShipmentBid::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(ShipmentLeg::class)->orderBy('sequence');
    }

    public function paymentReference(): HasOne
    {
        return $this->hasOne(ShipmentPaymentReference::class);
    }

    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_OPEN => [self::STATUS_CLAIMED, self::STATUS_CANCELLED],
            self::STATUS_CLAIMED => [self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED],
            self::STATUS_IN_TRANSIT => [self::STATUS_DELIVERED, self::STATUS_DISPUTED, self::STATUS_CANCELLED],
            self::STATUS_DELIVERED => [],
            self::STATUS_DISPUTED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (!in_array($status, $allowed[$this->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid transition from {$this->status} to {$status}."],
            ]);
        }

        $this->status = $status;
        $now = now();

        if ($status === self::STATUS_IN_TRANSIT) {
            $this->in_transit_at = $now;
        }

        if ($status === self::STATUS_DELIVERED) {
            $this->delivered_at = $now;
        }

        if ($status === self::STATUS_DISPUTED) {
            $this->disputed_at = $now;
        }

        if ($status === self::STATUS_CANCELLED) {
            $this->cancelled_at = $now;
        }

        $this->save();
    }
}
