<?php

namespace App\Support\Export;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\ContactStatus;
use App\Models\CustomFieldTemplate;
use App\Models\Group;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Data exports for GDPR requests and the end of a contract (umowa powierzenia, § 4 and § 8):
 *  - a whole restaurant (every card, private ones included),
 *  - one e-mail address (everything about one client of the restaurant),
 *  - one user account (the employee's own data).
 *
 * Emails are stored as metadata only (subject, sender, date, direction, Gmail ids,
 * snippet ≤ 2000 chars) in `activities` – full message bodies stay in Gmail.
 * Passwords, sign-in tokens and the calendar token are never exported.
 */
class DataExporter
{
    /** @return array<string, mixed> */
    public function restaurant(Group $group): array
    {
        return $this->inGroup($group, fn () => [
            'export' => $this->header('restaurant'),
            'restaurant' => $this->group($group),
            'users' => User::query()->where('group_id', $group->id)->orderBy('id')->get()->map(fn (User $u) => $this->userSummary($u))->all(),
            'settings' => [
                'contact_categories' => ContactCategory::query()->orderBy('sort_order')->get(['id', 'name', 'slug', 'color', 'icon', 'is_private'])->toArray(),
                'contact_statuses' => ContactStatus::query()->ordered()->get(['key', 'name', 'color', 'is_default'])->toArray(),
                'task_categories' => TaskCategory::query()->ordered()->get(['key', 'name', 'color', 'icon'])->toArray(),
                'field_templates' => CustomFieldTemplate::query()->orderBy('sort_order')->get(['label', 'type'])->toArray(),
            ],
            'contacts' => $this->contacts(Contact::query()->orderBy('id')->get()),
            'tasks_without_contact' => Task::query()->whereNull('contact_id')->orderBy('id')->get()->map(fn (Task $t) => $this->task($t))->all(),
            'reminders_without_contact' => Reminder::query()->whereNull('contact_id')->orderBy('id')->get()->map(fn (Reminder $r) => $this->reminder($r))->all(),
        ]);
    }

    /**
     * Everything the restaurant keeps about one e-mail address (all cards:
     * the shared one or every person's private card in business correspondence).
     *
     * @return array<string, mixed>
     */
    public function contactEmail(Group $group, string $email): array
    {
        return $this->inGroup($group, fn () => [
            'export' => $this->header('contact', ['email' => mb_strtolower(trim($email))]),
            'restaurant' => $this->group($group),
            'contacts' => $this->contacts(Contact::query()->where('email', mb_strtolower(trim($email)))->orderBy('id')->get()),
        ]);
    }

    /** A user's own account data (employee request). @return array<string, mixed> */
    public function user(User $user): array
    {
        $group = $user->group;
        $data = fn () => [
            'export' => $this->header('user', ['email' => $user->email]),
            'account' => $this->userSummary($user) + [
                'restaurant' => $group?->name,
                'preferences' => $user->preferences(),
                'calendar_feed_enabled' => $user->calendar_token_hash !== null,
            ],
            'sign_in_tokens' => $user->tokens()->orderBy('id')->get()->map(fn ($t) => [
                'device' => $t->name,
                'created_at' => $this->date($t->created_at),
                'last_used_at' => $this->date($t->last_used_at),
                'expires_at' => $this->date($t->expires_at),
            ])->all(),
            'google_calendar_events' => CalendarEvent::query()->where('user_id', $user->id)->get()->map(fn (CalendarEvent $e) => [
                'for' => $e->eventable_type.' #'.$e->eventable_id,
                'provider' => $e->provider,
                'external_id' => $e->external_id,
            ])->all(),
            'owned_contacts' => Contact::query()->where('user_id', $user->id)->orderBy('id')->get(['id', 'email', 'name', 'personal_key'])
                ->map(fn (Contact $c) => ['id' => $c->id, 'email' => $c->email, 'name' => $c->name, 'private_card' => $c->isPersonal()])->all(),
            'notes_written' => Activity::query()->where('user_id', $user->id)->where('type', 'note')->with('contact:id,email')->orderBy('occurred_at')->get()
                ->map(fn (Activity $a) => ['contact' => $a->contact?->email, 'body' => $a->body, 'occurred_at' => $this->date($a->occurred_at)])->all(),
            'tasks_created_or_assigned' => Task::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id))
                ->orderBy('id')->get()->map(fn (Task $t) => $this->task($t))->all(),
            'reminders' => Reminder::query()->where('user_id', $user->id)->orderBy('id')->get()->map(fn (Reminder $r) => $this->reminder($r))->all(),
        ];

        return $group === null ? $data() : $this->inGroup($group, $data);
    }

    /**
     * Contacts as rows for a spreadsheet (Excel, ";" separated).
     *
     * @return list<array<int, string|int|null>>
     */
    public function contactRows(Group $group): array
    {
        return $this->inGroup($group, function () {
            $rows = [['ID', 'E-mail', 'Imię i nazwisko', 'Firma', 'Telefon', 'Status', 'Kategoria', 'Klient', 'Opiekun', 'Prywatna karta', 'Pola dodatkowe', 'Notatki', 'Utworzono']];
            Contact::query()->with(['category', 'owner:id,email', 'customFields'])->orderBy('id')->each(function (Contact $c) use (&$rows) {
                $rows[] = [
                    $c->id, $c->email, $c->name, $c->company, $c->phone, $c->status, $c->category?->name,
                    $c->is_client ? 'tak' : 'nie', $c->owner?->email, $c->isPersonal() ? 'tak' : 'nie',
                    $c->customFields->map(fn ($f) => "{$f->label}: {$f->value}")->implode('; '),
                    $c->notes, $this->date($c->created_at),
                ];
            });

            return $rows;
        });
    }

    // ------------------------------------------------------------------

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function inGroup(Group $group, \Closure $callback): mixed
    {
        return app(Tenancy::class)->runAs($group, $callback);
    }

    /** @return array<string, mixed> */
    private function header(string $scope, array $extra = []): array
    {
        return ['scope' => $scope, 'generated_at' => now()->toIso8601String(), 'system' => 'GastroFlowx'] + $extra;
    }

    /** @return array<string, mixed> */
    private function group(Group $group): array
    {
        return $group->only(['id', 'name', 'slug', 'timezone', 'contact_email', 'phone']);
    }

    /** @return array<string, mixed> */
    private function userSummary(User $user): array
    {
        $license = $user->license;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'created_at' => $this->date($user->created_at),
            'license_expires_at' => $this->date($license?->expires_at),
        ];
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<array<string, mixed>>
     */
    private function contacts(Collection $contacts): array
    {
        $contacts->load([
            'category:id,name', 'owner:id,name,email', 'customFields', 'shares.user:id,email',
            'timeline' => fn ($q) => $q->with(['author:id,email', 'sharedBy:id,email']),
            'tasks.assignee:id,email', 'reminders', 'reservations',
            'accessRequests.requester:id,email',
        ]);

        return $contacts->map(fn (Contact $c) => [
            'id' => $c->id,
            'email' => $c->email,
            'name' => $c->name,
            'company' => $c->company,
            'phone' => $c->phone,
            'status' => $c->status,
            'category' => $c->category?->name,
            'is_client' => $c->is_client,
            'notes' => $c->notes,
            'owner' => $c->owner ? ['name' => $c->owner->name, 'email' => $c->owner->email] : null,
            'private_card' => $c->isPersonal(),
            'created_at' => $this->date($c->created_at),
            'updated_at' => $this->date($c->updated_at),
            'custom_fields' => $c->customFields->map(fn ($f) => ['label' => $f->label, 'type' => $f->type->value, 'value' => $f->value])->all(),
            'shared_with' => $c->shares->map(fn ($s) => [
                'with' => $s->user?->email ?? 'cały zespół',
                'scopes' => $s->scopes(),
                'email_ids' => $s->activity_ids ?? [],
            ])->all(),
            'access_requests' => $c->accessRequests->map(fn ($r) => [
                'from' => $r->requester?->email, 'status' => $r->status, 'message' => $r->message, 'created_at' => $this->date($r->created_at),
            ])->all(),
            'timeline' => $c->timeline->map(fn (Activity $a) => $this->activity($a))->all(),
            'tasks' => $c->tasks->map(fn (Task $t) => $this->task($t))->all(),
            'reminders' => $c->reminders->map(fn (Reminder $r) => $this->reminder($r))->all(),
            'reservations' => $c->reservations->map(fn (Reservation $r) => $this->reservation($r))->all(),
        ])->all();
    }

    /** @return array<string, mixed> */
    private function activity(Activity $a): array
    {
        $row = [
            'id' => $a->id,
            'type' => $a->type->value,   // email | note | system
            'occurred_at' => $this->date($a->occurred_at),
            'author' => $a->author?->email,
        ];

        return $row + match ($a->type->value) {
            'email' => [
                'subject' => $a->title,
                'snippet' => $a->body,
                'from' => $a->meta['from'] ?? null,
                'direction' => $a->meta['direction'] ?? null,
                'gmail_message_id' => $a->meta['message_id'] ?? null,
                'gmail_thread_id' => $a->meta['thread_id'] ?? null,
                'team_pool' => $a->team_shared_at !== null,
                'team_pool_by' => $a->sharedBy?->email,
            ],
            'note' => ['body' => $a->body],
            default => ['event' => $a->event, 'details' => $a->meta],
        };
    }

    /** @return array<string, mixed> */
    private function task(Task $t): array
    {
        return [
            'id' => $t->id, 'title' => $t->title, 'description' => $t->description, 'type' => $t->type,
            'priority' => $t->priority?->value, 'due_date' => $this->date($t->due_date), 'is_completed' => $t->is_completed,
            'assigned_to' => $t->assignee?->email, 'source_email_subject' => $t->source_email_subject,
            'created_at' => $this->date($t->created_at),
        ];
    }

    /** @return array<string, mixed> */
    private function reminder(Reminder $r): array
    {
        return [
            'id' => $r->id, 'title' => $r->title, 'notes' => $r->notes, 'type' => $r->type?->value,
            'remind_at' => $this->date($r->remind_at), 'is_done' => $r->is_done,
            'reservation_id' => $r->reservation_id, 'source_email_subject' => $r->source_email_subject,
        ];
    }

    /** @return array<string, mixed> */
    private function reservation(Reservation $r): array
    {
        return [
            'id' => $r->id, 'date' => $r->reservation_date?->format('Y-m-d'), 'time' => substr((string) $r->reservation_time, 0, 5),
            'guests' => $r->guests_count, 'status' => $r->status?->value, 'table' => $r->table_label,
            'occasion' => $r->occasion, 'notes' => $r->notes, 'created_at' => $this->date($r->created_at),
        ];
    }

    private function date(?Carbon $date): ?string
    {
        return $date?->toIso8601String();
    }
}
