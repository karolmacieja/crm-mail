<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\IsGroupDictionary;
use Illuminate\Database\Eloquent\Model;

/**
 * Contact status configured by the restaurant (Lead, Klient, ...).
 * contacts.status stores the `key`.
 */
class ContactStatus extends Model
{
    use BelongsToGroup, IsGroupDictionary;

    protected $fillable = ['key', 'name', 'color', 'sort_order', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Status given to new contacts of a group. */
    public static function defaultKeyFor(int $groupId): string
    {
        return static::query()->forGroup($groupId)->orderByDesc('is_default')->ordered()->value('key') ?? 'lead';
    }

    public function contactsCount(): int
    {
        return Contact::query()->forGroup($this->group_id)->where('status', $this->key)->count();
    }
}
