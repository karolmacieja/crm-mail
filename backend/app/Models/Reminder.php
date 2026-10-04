<?php

namespace App\Models;

use App\Enums\ReminderType;
use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
use App\Models\Concerns\HasDueWindows;
use App\Models\Concerns\SharedThroughContact;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * "Przypomnienie": a moment to come back to an email, reservation or contact.
 * Time status (overdue / today / 7 days) comes from HasDueWindows.
 */
class Reminder extends Model
{
    use BelongsToGroup, HasActivities, HasDueWindows, SharedThroughContact;

    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /** Contact share section that reveals these records. */
    public const SHARE_SCOPE = 'work';

    public const DUE_COLUMN = 'remind_at';

    public const DONE_COLUMN = 'is_done';

    protected $fillable = [
        'contact_id',
        'reservation_id',
        'type',
        'title',
        'notes',
        'remind_at',
        'is_done',
        'source_email_id',
        'source_email_subject',
    ];

    protected $attributes = [
        'type' => 'general',
        'is_done' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'remind_at' => 'datetime',
            'is_done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Polymorphic links have no FK cascade.
        static::deleting(fn (Reminder $item) => $item->calendarEvents()->delete());

        static::saving(function (Reminder $reminder) {
            if ($reminder->isDirty('is_done')) {
                $reminder->done_at = $reminder->is_done ? now() : null;
            }

            // A reminder about a reservation also shows on that guest's card.
            if ($reminder->reservation_id !== null && $reminder->contact_id === null) {
                $reminder->contact_id = $reminder->reservation?->contact_id;
            }
        });
    }

    public function calendarEvents(): MorphMany
    {
        return $this->morphMany(CalendarEvent::class, 'eventable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
