<?php

namespace App\Models;

use App\Models\Concerns\BelongsToNode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fleet extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToNode;

    protected $fillable = [
        'node_id',
        'name',
        'status',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
