<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'transport_law_attestation_hash',
        'platform_indemnification_attestation_hash',
        'transport_capabilities',
        'governance_room_id',
        'reputation_score',
        'is_active',
        'activated_at',
    ];

    protected $casts = [
        'contact' => 'array',
        'transport_capabilities' => 'array',
        'service_radius' => 'decimal:2',
        'reputation_score' => 'decimal:2',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
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

    public function transportClasses(): BelongsToMany
    {
        return $this->belongsToMany(TransportClass::class, 'node_transport_classes')
            ->withTimestamps();
    }

    public function trustScore(): HasOne
    {
        return $this->hasOne(NodeTrustScore::class);
    }

    public function attestationAcceptances(): HasMany
    {
        return $this->hasMany(NodeAttestationAcceptance::class);
    }

    public function hasCompletedAttestation(): bool
    {
        return !empty($this->transport_law_attestation_hash)
            && !empty($this->license_attestation_hash)
            && !empty($this->insurance_attestation_hash)
            && !empty($this->platform_indemnification_attestation_hash);
    }
}
