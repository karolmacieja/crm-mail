<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Time-window scopes shared by tasks and reminders:
 * overdue / due today / next 7 days, computed in the user's timezone.
 *
 * Models define DUE_COLUMN (e.g. 'due_date', 'remind_at') and
 * DONE_COLUMN (e.g. 'is_completed', 'is_done').
 */
trait HasDueWindows
{
    public const UPCOMING_DAYS = 7;

    public function scopePending(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::DONE_COLUMN), false);
    }

    /** Not done and already past due. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()
            ->whereNotNull($this->qualifyColumn(static::DUE_COLUMN))
            ->where($this->qualifyColumn(static::DUE_COLUMN), '<', Carbon::now());
    }

    /** Not done, due later today (local day of $timezone). */
    public function scopeDueToday(Builder $query, ?string $timezone = null): Builder
    {
        return $query->pending()
            ->whereBetween($this->qualifyColumn(static::DUE_COLUMN), [Carbon::now(), static::endOfLocalDay($timezone)]);
    }

    /** Not done, due after today and within the next N days. */
    public function scopeUpcoming(Builder $query, ?string $timezone = null, int $days = self::UPCOMING_DAYS): Builder
    {
        $endOfToday = static::endOfLocalDay($timezone);

        return $query->pending()
            ->where($this->qualifyColumn(static::DUE_COLUMN), '>', $endOfToday)
            ->where($this->qualifyColumn(static::DUE_COLUMN), '<=', $endOfToday->copy()->addDays($days));
    }

    /**
     * 'done' | 'overdue' | 'today' | 'upcoming' | 'later' | 'unscheduled'
     */
    public function timeStatus(?string $timezone = null): string
    {
        if ($this->getAttribute(static::DONE_COLUMN)) {
            return 'done';
        }

        /** @var Carbon|null $due */
        $due = $this->getAttribute(static::DUE_COLUMN);
        $endOfToday = static::endOfLocalDay($timezone);

        return match (true) {
            $due === null => 'unscheduled',
            $due->isPast() => 'overdue',
            $due->lessThanOrEqualTo($endOfToday) => 'today',
            $due->lessThanOrEqualTo($endOfToday->copy()->addDays(self::UPCOMING_DAYS)) => 'upcoming',
            default => 'later',
        };
    }

    protected static function endOfLocalDay(?string $timezone): Carbon
    {
        return Carbon::now()->setTimezone($timezone ?? config('app.timezone'))->endOfDay()->utc();
    }
}
