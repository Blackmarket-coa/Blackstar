<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TransportClass extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'category',
        'subtype',
        'weight_limit',
        'range_limit',
        'hazard_capability',
        'regulatory_class',
        'insurance_required_flag',
    ];

    protected $casts = [
        'weight_limit' => 'decimal:2',
        'range_limit' => 'decimal:2',
        'hazard_capability' => 'boolean',
        'insurance_required_flag' => 'boolean',
    ];

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_transport_classes')
            ->withTimestamps();
    }
}
