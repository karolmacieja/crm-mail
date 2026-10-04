<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
use App\Support\Sharing\ContactAccess;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person or company the restaurant talks to.
 * is_client = false: address-book entry ("Kontakty");
 * is_client = true:  full client profile ("Klienci").
 */
class Contact extends Model
{
    use BelongsToGroup, HasActivities;

    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'email',
        'name',
        'company',
        'phone',
        'status',
        'is_client',
        'notes',
    ];

    protected $attributes = [
        'is_client' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_client' => 'boolean',
            'personal_key' => 'integer',
            'last_activity_at' => 'datetime',
            'email_history_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // BelongsToGroup has filled group_id already; new contacts get the restaurant's default status.
        static::creating(function (Contact $contact) {
            $contact->status ??= ContactStatus::defaultKeyFor($contact->group_id);
        });

        // A private category ("korespondencja firmowa") makes the card personal to its owner.
        static::saving(function (Contact $contact) {
            if (! $contact->exists || $contact->isDirty(['category_id', 'user_id'])) {
                $contact->personal_key = static::isPrivateCategory($contact->category_id) && $contact->user_id !== null
                    ? $contact->user_id
                    : 0;
            }
        });
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Activity::class)->where('type', 'note');
    }

    /** The restaurant's latest manual notes (shown under "Dane kontaktowe"). */
    public function recentNotes(): HasMany
    {
        return $this->hasMany(Activity::class)->where('type', 'note')->latest('occurred_at')->latest('id');
    }

    /**
     * Emails are stored lower-cased so Gmail lookups are case-insensitive.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    /** @var array<int, ContactAccess> */
    private array $accessCache = [];

    /**
     * The contact's owner ("opiekun"): its creator, unless handed over.
     * Null when that user was removed – such contacts are shared with the team.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Who created the contact (null if that user was removed). */
    public function creator(): BelongsTo
    {
        return $this->owner();
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ContactShare::class);
    }

    /** Eager-load with a forViewer() constraint to resolve access without extra queries. */
    public function viewerShares(): HasMany
    {
        return $this->hasMany(ContactShare::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(ContactAccessRequest::class);
    }

    public static function isPrivateCategory(?int $categoryId): bool
    {
        return $categoryId !== null
            && (bool) ContactCategory::withoutGlobalScopes()->whereKey($categoryId)->value('is_private');
    }

    /** A personal card in a private category: only its owner sees it, never shared. */
    public function isPersonal(): bool
    {
        return (int) $this->personal_key !== 0;
    }

    /**
     * Colleagues' personal cards of the same e-mail (their emails added to
     * the team pool show on this card's timeline).
     */
    public function siblingCards(): Builder
    {
        return static::query()->where('email', $this->email)->where('personal_key', '!=', 0)->whereKeyNot($this->getKey());
    }

    /** What $user may see and do on this card (memoized per instance). */
    public function accessFor(User $user): ContactAccess
    {
        return $this->accessCache[$user->id] ??= ContactAccess::resolve($user, $this);
    }

    public function forgetAccess(): void
    {
        $this->accessCache = [];
        $this->unsetRelation('viewerShares');
    }

    /** @deprecated use creator(); kept for existing API code until step 2. */
    public function user(): BelongsTo
    {
        return $this->creator();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContactCategory::class, 'category_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(ContactCustomField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function openTasks(): HasMany
    {
        return $this->tasks()->where('is_completed', false);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->orderBy('reservation_date')->orderBy('reservation_time');
    }

    public function upcomingReservations(): HasMany
    {
        return $this->reservations()->upcoming();
    }

    /**
     * Full timeline for the client card: entries about the contact itself
     * plus those about its reservations, tasks and reminders.
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('occurred_at')->latest('id');
    }

    /** Newest timeline entry ("Ostatnia aktywność" column). */
    public function latestActivity(): HasOne
    {
        return $this->hasOne(Activity::class)->latestOfMany('occurred_at');
    }

    /** Add a manual note to the timeline. */
    public function addNote(string $body, ?User $author = null): Activity
    {
        return Activity::recordNote($this, $body, $author);
    }

    /**
     * Contacts $user can open: own (incl. personal cards), ownerless, or shared
     * with them / the team. Colleagues' personal cards are never visible.
     * With $scope, only those whose share covers that section (owners always do).
     */
    public function scopeVisibleTo(Builder $query, User $user, ?string $scope = null): Builder
    {
        static::constrainVisible($query, $user->id, $scope, $this->getTable());

        return $query;
    }

    /** Contacts owned by $user ("Moje"). */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where($this->qualifyColumn('user_id'), $user->id);
    }

    /**
     * The visibility condition on a plain query builder as well (used by the
     * Exists validation rules), so both stay identical.
     *
     * @param  \Illuminate\Database\Query\Builder|Builder  $query
     */
    public static function constrainVisible($query, int $userId, ?string $scope = null, string $table = 'contacts'): void
    {
        $query->where(function ($q) use ($userId, $scope, $table) {
            $q->where("{$table}.user_id", $userId)
                ->orWhere(fn ($q) => $q->where("{$table}.personal_key", 0)->where(function ($q) use ($userId, $scope, $table) {
                    $q->whereNull("{$table}.user_id")
                        ->orWhereExists(function ($shares) use ($userId, $scope, $table) {
                            $shares->selectRaw('1')->from('contact_shares')
                                ->whereColumn('contact_shares.contact_id', "{$table}.id")
                                ->where(fn ($w) => $w->whereNull('contact_shares.user_id')->orWhere('contact_shares.user_id', $userId));
                            if ($scope !== null) {
                                $shares->where("contact_shares.{$scope}", true);
                            }
                        });
                }));
        });
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('is_client', true);
    }

    public function scopeInCategory(Builder $query, ContactCategory|int|string|null $category): Builder
    {
        return match (true) {
            $category === null, $category === '' => $query,
            $category instanceof ContactCategory => $query->where('category_id', $category->getKey()),
            is_numeric($category) => $query->where('category_id', (int) $category),
            default => $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category)),
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('email', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    protected function contactIdForTimeline(): ?int
    {
        return $this->getKey();
    }
}
