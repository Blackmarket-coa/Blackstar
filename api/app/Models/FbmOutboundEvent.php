<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbmOutboundEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'event_type',
        'correlation_id',
        'payload',
        'signature',
        'status',
        'attempts',
        'last_error',
        'dispatched_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'dispatched_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];
}
