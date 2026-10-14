<?php

namespace App\Support\Reservations;

use App\Enums\ReminderType;
use App\Enums\ReservationStatus;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\User;

/**
 * "Przypomnienie o rezerwacji": a reminder for the person who added the
 * booking, N hours before it starts (personal preference). It moves with the
 * booking's date and is closed when the booking is cancelled or finished.
 */
class ReservationReminder
{
    public static function sync(Reservation $reservation): ?Reminder
    {
        $existing = Reminder::withoutGlobalScopes()->where('reservation_id', $reservation->id)->where('auto_reservation', true)->first();
        $owner = $reservation->user_id ? User::find($reservation->user_id) : null;
        $prefs = $owner?->preferences()['reservations'] ?? [];
        $open = in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);
        $startsAt = $reservation->startsAt();

        if (! $open || $startsAt->isPast()) {
            if ($existing && ! $existing->is_done) {
                $existing->forceFill(['is_done' => true])->save();
            }

            return $existing;
        }

        if ($existing === null && ! ($prefs['auto_reminder'] ?? false)) {
            return null;
        }

        // Too late for the full advance notice: remind right away.
        $remindAt = $startsAt->copy()->subHours((int) ($prefs['reminder_hours_before'] ?? 24))->utc();
        if ($remindAt->isPast()) {
            $remindAt = now();
        }

        $reminder = $existing ?? new Reminder;
        $reminder->fill([
            'type' => ReminderType::Reservation,
            'title' => self::title($reservation),
            'remind_at' => $remindAt,
            'is_done' => false,
            'contact_id' => $reservation->contact_id,
            'reservation_id' => $reservation->id,
        ]);
        $reminder->user_id ??= $reservation->user_id;
        $reminder->group_id = $reservation->group_id;
        $reminder->auto_reservation = true;
        $reminder->save();

        return $reminder;
    }

    private static function title(Reservation $reservation): string
    {
        $contact = $reservation->contact;

        return __('crm.reservation_reminder_title', [
            'name' => $contact?->name ?: $contact?->email ?: '—',
            'when' => $reservation->reservation_date->format('d.m').' '.substr((string) $reservation->reservation_time, 0, 5),
            'count' => $reservation->guests_count,
        ]);
    }
}
