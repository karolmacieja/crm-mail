<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\IsGroupDictionary;
use Illuminate\Database\Eloquent\Model;

/**
 * Task category = a column of the tasks board (Follow-up, Oferty, ...).
 * tasks.type stores the `key`.
 */
class TaskCategory extends Model
{
    use BelongsToGroup, IsGroupDictionary;

    protected $fillable = ['key', 'name', 'color', 'icon', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public static function defaultKeyFor(int $groupId): string
    {
        return static::query()->forGroup($groupId)->ordered()->value('key') ?? 'follow_up';
    }

    public function tasksCount(): int
    {
        return Task::query()->forGroup($this->group_id)->where('type', $this->key)->count();
    }
}
