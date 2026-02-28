<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPaymentReference extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'shipment_board_listing_id',
        'buyer_vendor_payment_ref',
        'vendor_node_settlement_ref',
        'platform_fee_ref',
        'correlation_id',
        'recorded_by_user_id',
    ];

    public function shipmentBoardListing(): BelongsTo
    {
        return $this->belongsTo(ShipmentBoardListing::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
