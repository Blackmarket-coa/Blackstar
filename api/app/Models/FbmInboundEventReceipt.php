<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbmInboundEventReceipt extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'event_id',
        'event_type',
        'correlation_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'processed_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];
}
