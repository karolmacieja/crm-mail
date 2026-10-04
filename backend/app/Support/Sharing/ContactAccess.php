<?php

namespace App\Support\Sharing;

use App\Models\Contact;
use App\Models\ContactShare;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What one person may see and do on one contact card.
 *
 *  - owner ("opiekun", contacts.user_id) – everything, incl. sharing;
 *  - contact without an owner (creator removed) – shared with the whole team;
 *  - anyone else – only through shares (to the team or to them personally),
 *    which add up: the sections they cover plus individually shared emails.
 *
 * People with access always see their own entries (notes, tasks, reminders,
 * reservations, logged emails) and may add new ones; only the owner changes
 * the contact itself or other people's entries.
 */
final class ContactAccess
{
    /** Sections a share can cover. */
    public const SCOPES = ['details', 'notes', 'emails', 'reservations', 'work'];

    /**
     * @param  list<string>  $scopes
     * @param  list<int>  $activityIds
     */
    public function __construct(
        public readonly bool $canView,
        public readonly bool $canManage,
        public readonly array $scopes,
        public readonly array $activityIds = [],
    ) {}

    public static function resolve(User $user, Contact $contact): self
    {
        if ($contact->user_id === null || $contact->user_id === $user->id) {
            return new self(true, true, self::SCOPES);
        }

        /** @var Collection<int, ContactShare> $shares */
        $shares = $contact->relationLoaded('viewerShares')
            ? $contact->viewerShares
            : $contact->shares()->forViewer($user)->get();

        $shares = $shares->filter(fn (ContactShare $s) => $s->user_id === null || $s->user_id === $user->id);
        if ($shares->isEmpty()) {
            return new self(false, false, []);
        }

        $scopes = array_values(array_filter(self::SCOPES, fn (string $scope) => $shares->contains(fn (ContactShare $s) => (bool) $s->{$scope})));
        $ids = $shares->flatMap(fn (ContactShare $s) => $s->activity_ids ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        return new self(true, false, $scopes, $ids);
    }

    public function has(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /** Full access to every section (owner, or shared with everything). */
    public function isFull(): bool
    {
        return $this->canManage || count($this->scopes) === count(self::SCOPES);
    }
}
