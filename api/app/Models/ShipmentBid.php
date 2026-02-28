<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentBid extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'shipment_board_listing_id',
        'node_id',
        'amount',
        'currency',
        'note',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ShipmentBoardListing::class, 'shipment_board_listing_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
