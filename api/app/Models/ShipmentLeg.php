<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ShipmentLeg extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_HANDED_OFF = 'handed_off';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'shipment_board_listing_id',
        'sequence',
        'from_node_id',
        'to_node_id',
        'status',
        'proof_of_handoff_hash',
        'settlement_ref',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShipmentBoardListing::class, 'shipment_board_listing_id');
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'to_node_id');
    }

    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_IN_TRANSIT, self::STATUS_FAILED, self::STATUS_DISPUTED],
            self::STATUS_IN_TRANSIT => [self::STATUS_HANDED_OFF, self::STATUS_FAILED, self::STATUS_DISPUTED],
            self::STATUS_HANDED_OFF => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_DISPUTED],
            self::STATUS_COMPLETED => [],
            self::STATUS_FAILED => [],
            self::STATUS_DISPUTED => [],
        ];

        if (!in_array($status, $allowed[$this->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid leg transition from {$this->status} to {$status}."],
            ]);
        }

        $this->status = $status;
        $this->save();
    }
}
