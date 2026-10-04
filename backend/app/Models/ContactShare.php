<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Support\Sharing\ContactAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact shared by its owner with the whole team (user_id = null) or one
 * colleague, limited to the chosen sections and/or individual emails.
 *
 * @property list<int>|null $activity_ids
 */
class ContactShare extends Model
{
    use BelongsToGroup;

    protected $fillable = [
        'user_id',
        'details',
        'notes',
        'emails',
        'reservations',
        'work',
        'activity_ids',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'boolean',
            'notes' => 'boolean',
            'emails' => 'boolean',
            'reservations' => 'boolean',
            'work' => 'boolean',
            'activity_ids' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** The colleague (null = whole team). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Shares that apply to this person: theirs and the team-wide one. */
    public function scopeForViewer(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return array_values(array_filter(ContactAccess::SCOPES, fn (string $scope) => (bool) $this->{$scope}));
    }
}
