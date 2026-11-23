<?php

namespace App\Models\Concerns;

use App\Support\StudyMode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every query against a track-scoped model is transparently filtered to the
 * active track, and every new row is stamped with it — so the ~40 existing
 * call sites across the app stay mode-correct without being touched. Bulk
 * operations that bypass Eloquent entirely (upsert, raw DB::table queries)
 * are NOT covered by this and must set `mode` explicitly.
 */
trait ScopedToMode
{
    public static function bootScopedToMode(): void
    {
        static::addGlobalScope('mode', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable().'.mode', StudyMode::current());
        });

        static::creating(function ($model) {
            $model->mode ??= StudyMode::current();
        });
    }

    /** Escape hatch for the rare cross-track admin query (e.g. the danger-zone reset). */
    public function scopeAnyMode(Builder $query): Builder
    {
        return $query->withoutGlobalScope('mode');
    }
}
