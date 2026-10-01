<?php

namespace App\Models\Concerns;

use App\Models\Group;
use App\Models\Scopes\GroupScope;
use App\Support\Tenancy\MissingGroupContext;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenancy for operational models (contacts, tasks, reservations, ...):
 *  - adds GroupScope so queries only see the current group's rows;
 *  - fills group_id automatically on create and refuses to create orphans.
 *
 * @property int $group_id
 */
trait BelongsToGroup
{
    public static function bootBelongsToGroup(): void
    {
        static::addGlobalScope(new GroupScope);

        static::creating(function ($model) {
            $model->group_id ??= app(Tenancy::class)->id();

            if ($model->group_id === null) {
                throw MissingGroupContext::forModel(static::class);
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Explicitly query another group's rows (admin panel, reports). */
    public function scopeForGroup(Builder $query, Group|int $group): Builder
    {
        return $query->withoutGlobalScope(GroupScope::class)
            ->where($this->qualifyColumn('group_id'), $group instanceof Group ? $group->getKey() : $group);
    }
}
