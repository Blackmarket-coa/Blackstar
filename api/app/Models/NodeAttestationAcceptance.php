<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeAttestationAcceptance extends Model
{
    use HasFactory;
    use HasUuids;

    public const TERM_TRANSPORT_LAW = 'transport_law_compliance';
    public const TERM_LICENSE_AND_INSURANCE = 'license_and_insurance_responsibility';
    public const TERM_PLATFORM_INDEMNIFICATION = 'platform_indemnification';

    protected $fillable = [
        'node_id',
        'accepted_by_user_id',
        'term_key',
        'signed_hash_artifact',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }
}
