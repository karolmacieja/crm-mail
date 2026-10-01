<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Shared behaviour of the settings dictionaries (statuses, task categories):
 * a stable `key` derived from the name, ordered by sort_order.
 *
 * @property string $key
 * @property string $name
 */
trait IsGroupDictionary
{
    public static function bootIsGroupDictionary(): void
    {
        static::creating(function ($model) {
            $model->key ??= static::uniqueKey($model->group_id, $model->name);
            $model->sort_order ??= (int) static::query()->forGroup($model->group_id)->max('sort_order') + 1;
        });
    }

    public static function uniqueKey(int $groupId, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'item';
        $key = $base;
        $i = 2;
        while (static::query()->forGroup($groupId)->where('key', $key)->exists()) {
            $key = "{$base}_{$i}";
            $i++;
        }

        return $key;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
