<?php

namespace App\Models;

use App\Models\Concerns\BelongsToNode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToNode;

    protected $fillable = [
        'node_id',
        'fleet_id',
        'name',
        'plate_number',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
