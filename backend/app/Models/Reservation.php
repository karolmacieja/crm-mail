<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToGroup;
use App\Models\Concerns\HasActivities;
use App\Models\Concerns\SharedThroughContact;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        static::created(function (Reservation $reservation) {
            $reservation->recordSystemActivity('reservation.created', $reservation->summary());
        });

        static::updated(function (Reservation $reservation) {
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

    /** Start of the booking in the group's timezone. */
    public function startsAt(?string $timezone = null): Carbon
    {
        $timezone ??= $this->group?->timezone ?? config('app.timezone');

        return Carbon::parse($this->reservation_date->format('Y-m-d').' '.$this->reservation_time, $timezone);
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
