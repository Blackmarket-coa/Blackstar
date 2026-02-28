<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToNode
{
    public static function bootBelongsToNode(): void
    {
        static::creating(function ($model) {
            if (empty($model->node_id) && auth()->check()) {
                $model->node_id = auth()->user()->node_id;
            }
        });

        static::addGlobalScope('node_tenancy', function (Builder $builder) {
            $user = auth()->user();

            if (!$user || !$user->node_id) {
                return;
            }

            $builder->where($builder->qualifyColumn('node_id'), $user->node_id);
        });
    }

    public function scopeForNode(Builder $query, string $nodeId): Builder
    {
        return $query->withoutGlobalScope('node_tenancy')->where('node_id', $nodeId);
    }
}
