<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
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
        'status' => 'lead',
        'is_client' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'is_client' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
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

    /** Who created the contact (null if that user was removed). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
