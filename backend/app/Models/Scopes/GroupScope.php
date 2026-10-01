<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on tenant-owned models to the current group.
 * A signed-in user without a group gets no rows at all (fail closed).
 */
class GroupScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        if (! $tenancy->shouldScope()) {
            return;
        }

        $groupId = $tenancy->id();
        $column = $model->qualifyColumn('group_id');

        $groupId === null
            ? $builder->whereRaw('1 = 0')
            : $builder->where($column, $groupId);
    }
}
