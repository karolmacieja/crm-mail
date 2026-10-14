<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
use App\Models\Concerns\SharedThroughContact;
use App\Support\Reservations\ReservationReminder;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Table / event booking. Date and time are stored separately in the
 * restaurant's local time (what the guest asked for), not converted to UTC.
 *
 * @property Carbon $reservation_date
 * @property string $reservation_time "HH:MM:SS"
 */
class Reservation extends Model
{
    use BelongsToGroup, HasActivities, SharedThroughContact;

    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /** Contact share section that reveals these records. */
    public const SHARE_SCOPE = 'reservations';

    protected $fillable = [
        'contact_id',
        'reservation_date',
        'reservation_time',
        'guests_count',
        'status',
        'table_label',
        'occasion',
        'notes',
        'source_email_id',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date:Y-m-d',
            'guests_count' => 'integer',
            'status' => ReservationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Polymorphic links have no FK cascade.
        static::deleting(fn (Reservation $item) => $item->calendarEvents()->delete());

        static::created(function (Reservation $reservation) {
            $reservation->recordSystemActivity('reservation.created', $reservation->summary());
            ReservationReminder::sync($reservation);
        });

        static::updated(function (Reservation $reservation) {
            if ($reservation->wasChanged(['reservation_date', 'reservation_time', 'status', 'guests_count', 'contact_id'])) {
                ReservationReminder::sync($reservation);
            }

            if ($reservation->wasChanged('status')) {
                $reservation->recordSystemActivity('reservation.status_changed', $reservation->summary() + [
                    'from' => $reservation->getOriginal('status')?->value,
                    'to' => $reservation->status->value,
                ]);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /** Reminder created automatically for this booking (preference). */
    public function autoReminder(): HasOne
    {
        return $this->hasOne(Reminder::class)->where('auto_reservation', true);
    }

    public function calendarEvents(): MorphMany
    {
        return $this->morphMany(CalendarEvent::class, 'eventable');
    }

    /** Start of the booking in the group's timezone. */
    public function startsAt(?string $timezone = null): Carbon
    {
        $timezone ??= $this->group?->timezone ?? config('app.timezone');

        return Carbon::parse($this->reservation_date->format('Y-m-d').' '.$this->reservation_time, $timezone);
    }

    /** Still open: pending or confirmed. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [ReservationStatus::Pending, ReservationStatus::Confirmed]);
    }

    /** Open bookings from past days – waiting to be marked completed / no-show ("Zaległe"). */
    public function scopeOverdue(Builder $query, ?string $timezone = null): Builder
    {
        return $query->open()->whereDate($this->qualifyColumn('reservation_date'), '<', static::localToday($timezone));
    }

    /** Open bookings for today (group-local date), whatever the hour. */
    public function scopeDueToday(Builder $query, ?string $timezone = null): Builder
    {
        return $query->open()->whereDate($this->qualifyColumn('reservation_date'), static::localToday($timezone));
    }

    /** Open bookings after today, within the next N days ("7 dni"). */
    public function scopeUpcomingWeek(Builder $query, ?string $timezone = null, int $days = 7): Builder
    {
        $today = Carbon::parse(static::localToday($timezone));

        return $query->open()
            ->whereDate($this->qualifyColumn('reservation_date'), '>', $today->toDateString())
            ->whereDate($this->qualifyColumn('reservation_date'), '<=', $today->copy()->addDays($days)->toDateString());
    }

    public static function localToday(?string $timezone = null): string
    {
        return Carbon::now($timezone ?? config('app.timezone'))->toDateString();
    }

    /** 'overdue' | 'today' | 'upcoming' | 'later' | 'closed' */
    public function timeStatus(?string $timezone = null): string
    {
        if (! in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            return 'closed';
        }
        $today = static::localToday($timezone);
        $date = $this->reservation_date->format('Y-m-d');

        return match (true) {
            $date < $today => 'overdue',
            $date === $today => 'today',
            $date <= Carbon::parse($today)->addDays(7)->toDateString() => 'upcoming',
            default => 'later',
        };
    }

    /** Not cancelled/finished and today or later (group-local date). */
    public function scopeUpcoming(Builder $query, ?string $timezone = null): Builder
    {
        $today = Carbon::now($timezone ?? config('app.timezone'))->toDateString();

        return $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->where('reservation_date', '>=', $today);
    }

    public function scopeOnDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('reservation_date', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'date' => $this->reservation_date?->format('Y-m-d'),
            'time' => substr((string) $this->reservation_time, 0, 5),
            'guests' => $this->guests_count,
            'status' => $this->status?->value,
        ];
    }
}
