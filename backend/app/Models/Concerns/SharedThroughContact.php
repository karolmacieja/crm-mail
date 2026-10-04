<?php

namespace App\Models\Concerns;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tasks, reminders and reservations follow their contact's sharing.
 * Visible to: their author; anyone when there is no contact (internal work);
 * and people who can open the contact with the section in SHARE_SCOPE.
 * Changed by: their author, or the contact's owner.
 */
trait SharedThroughContact
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where($this->qualifyColumn('user_id'), $user->id)
                ->orWhereNull($this->qualifyColumn('contact_id'))
                ->orWhereIn($this->qualifyColumn('contact_id'), Contact::query()->select('contacts.id')->visibleTo($user, static::SHARE_SCOPE));
            $this->alsoVisibleTo($q, $user);
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function canBeChangedBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->contact_id === null
            || $this->alsoChangeableBy($user)
            || ($this->contact?->accessFor($user)->canManage ?? false);
    }

    /** Extra "or" conditions, e.g. the assignee of a task. */
    protected function alsoVisibleTo(Builder $query, User $user): void {}

    protected function alsoChangeableBy(User $user): bool
    {
        return false;
    }
}
