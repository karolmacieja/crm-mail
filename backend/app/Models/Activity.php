<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToGroup;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Timeline entry ("Oś czasu"): email, manual note or system action.
 *
 * @property ActivityType $type
 * @property array<string, mixed>|null $meta
 */
class Activity extends Model
{
    use BelongsToGroup;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'type',
        'event',
        'title',
        'body',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity) {
            $activity->occurred_at ??= now();
        });

        // Feeds the "Ostatnia aktywność" column of the client list.
        static::created(function (Activity $activity) {
            if ($activity->contact_id === null) {
                return;
            }

            Contact::withoutGlobalScopes()
                ->whereKey($activity->contact_id)
                ->where(fn (Builder $q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $activity->occurred_at))
                ->update(['last_activity_at' => $activity->occurred_at]);
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function recordNote(Contact $contact, string $body, ?User $author = null): self
    {
        return static::recordFor($contact, [
            'type' => ActivityType::Note,
            'body' => $body,
            'user_id' => $author?->getKey() ?? auth()->id(),
        ]);
    }

    /**
     * Log an email from Gmail on the contact's timeline.
     *
     * @param  array{message_id: string, thread_id?: string, subject?: string, snippet?: string, from?: string, direction?: 'in'|'out', sent_at?: Carbon|string}  $email
     */
    public static function recordEmail(Contact $contact, array $email, ?User $user = null): self
    {
        return static::recordFor($contact, [
            'type' => ActivityType::Email,
            'title' => $email['subject'] ?? null,
            'body' => $email['snippet'] ?? null,
            'user_id' => $user?->getKey(),
            'meta' => array_filter([
                'message_id' => $email['message_id'],
                'thread_id' => $email['thread_id'] ?? null,
                'from' => $email['from'] ?? null,
                'direction' => $email['direction'] ?? 'in',
            ]),
            'occurred_at' => isset($email['sent_at']) ? Carbon::parse($email['sent_at']) : now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function recordFor(Contact $contact, array $attributes): self
    {
        $activity = $contact->activities()->make($attributes + ['contact_id' => $contact->getKey()]);
        $activity->group_id = $contact->group_id;
        $activity->save();

        return $activity;
    }

    public function scopeOfType(Builder $query, ActivityType|string|null $type): Builder
    {
        return $type === null || $type === '' ? $query : $query->where('type', $type);
    }
}
