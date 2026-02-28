<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'node_id',
        'legal_entity_name',
        'jurisdiction',
        'service_radius',
        'contact',
        'insurance_attestation_hash',
        'license_attestation_hash',
        'transport_capabilities',
        'governance_room_id',
        'reputation_score',
    ];

    protected $casts = [
        'contact' => 'array',
        'transport_capabilities' => 'array',
        'service_radius' => 'decimal:2',
        'reputation_score' => 'decimal:2',
    ];

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }
}
