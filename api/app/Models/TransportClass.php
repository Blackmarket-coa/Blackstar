<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'volume_limit',
        'range_limit',
        'hazard_capability',
        'regulatory_class',
        'insurance_required_flag',
    ];

    protected $casts = [
        'weight_limit' => 'decimal:2',
        'volume_limit' => 'decimal:2',
        'range_limit' => 'decimal:2',
        'hazard_capability' => 'boolean',
        'insurance_required_flag' => 'boolean',
    ];

    protected $appends = ['code'];

    /**
     * Stable human-readable handle, `category.subtype` (e.g. `ground.van`).
     * `(category, subtype)` is unique, so this identifies a class as well as
     * its uuid does and is what operators can send to
     * PUT /api/nodes/{node}/transport-classes instead of ids.
     */
    public static function codeFor(string $category, string $subtype): string
    {
        return $category . '.' . $subtype;
    }

    protected function code(): Attribute
    {
        return Attribute::get(fn () => static::codeFor((string) $this->category, (string) $this->subtype));
    }

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'node_transport_classes')
            ->withTimestamps();
    }
}
