<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use App\Support\Calendar\IcsCalendar;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Private iCalendar feed with a person's tasks, reminders and (optionally)
 * the restaurant's reservations. Subscribe once in Google Calendar /
 * Outlook / Apple Calendar ("from URL"); changes in the CRM show up there.
 */
class CalendarFeedController extends Controller
{
    /** POST /me/calendar-feed – create or rotate the private URL. */
    public function rotate(Request $request): JsonResponse
    {
        $token = $request->user()->rotateCalendarToken();

        return response()->json(['data' => ['url' => self::urlFor($token)]]);
    }

    /** DELETE /me/calendar-feed – the old URL stops working. */
    public function disable(Request $request): JsonResponse
    {
        $request->user()->disableCalendarFeed();

        return response()->json(null, 204);
    }

    public static function urlFor(?string $token): ?string
    {
        return $token === null ? null : route('calendar.feed', ['token' => $token]);
    }

    /** GET /calendar/{token}.ics – public, authenticated by the secret token only. */
    public function show(string $token): Response
    {
        $user = User::findByCalendarToken($token);
        abort_if($user === null || $user->group_id === null, 404);

        $group = $user->group;
        $calendar = new IcsCalendar("GastroFlowx – {$group->name}", __('crm.calendar.description', ['name' => $user->name]));

        // A lapsed license or deactivated restaurant yields an empty (but valid) calendar.
        if ($group->is_active && $user->hasValidLicense()) {
            app(Tenancy::class)->runAs($group, fn () => $this->fill($calendar, $user, $group->timezone));
        }

        return response($calendar->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="gastroflowx.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function fill(IcsCalendar $calendar, User $user, string $timezone): void
    {
        $prefs = $user->preferences();
        $alarm = (int) $prefs['calendar']['alarm_minutes'] ?: null;
        $from = now()->subDays(30);
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'gastroflowx';

        // Tasks assigned to me, or created by me and not assigned to anyone.
        Task::query()->pending()->with('contact:id,name,email')
            ->whereNotNull('due_date')->where('due_date', '>=', $from)
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere(fn ($q) => $q->whereNull('assigned_to')->where('user_id', $user->id)))
            ->orderBy('due_date')->limit(1000)->get()
            ->each(fn (Task $task) => $calendar->addEvent([
                'uid' => "task-{$task->id}@{$host}",
                'start' => $task->due_date,
                'end' => $task->due_date->copy()->addMinutes(30),
                'summary' => '✔ '.$task->title,
                'description' => $this->describe($task->contact, $task->description, $task->source_email_subject),
                'categories' => __('crm.calendar.task'),
                'alarm_minutes' => $alarm,
                'updated' => $task->updated_at,
            ]));

        Reminder::query()->pending()->with('contact:id,name,email')
            ->where('user_id', $user->id)->where('remind_at', '>=', $from)
            ->orderBy('remind_at')->limit(1000)->get()
            ->each(fn (Reminder $reminder) => $calendar->addEvent([
                'uid' => "reminder-{$reminder->id}@{$host}",
                'start' => $reminder->remind_at,
                'end' => $reminder->remind_at->copy()->addMinutes(15),
                'summary' => '🔔 '.$reminder->title,
                'description' => $this->describe($reminder->contact, $reminder->notes, $reminder->source_email_subject),
                'categories' => __('crm.calendar.reminder'),
                'alarm_minutes' => $alarm,
                'updated' => $reminder->updated_at,
            ]));

        if ($prefs['calendar']['include_reservations']) {
            Reservation::query()->with('contact:id,name,email')
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                ->where('reservation_date', '>=', Carbon::now($timezone)->subDays(7)->toDateString())
                ->orderBy('reservation_date')->limit(1000)->get()
                ->each(function (Reservation $reservation) use ($calendar, $host, $timezone) {
                    $start = $reservation->startsAt($timezone);
                    $calendar->addEvent([
                        'uid' => "reservation-{$reservation->id}@{$host}",
                        'start' => $start,
                        'end' => $start->copy()->addHours(2),
                        'summary' => '🍽 '.__('crm.calendar.reservation_summary', [
                            'name' => $reservation->contact?->name ?? $reservation->contact?->email ?? '—',
                            'count' => $reservation->guests_count,
                        ]),
                        'description' => collect([$reservation->occasion, $reservation->table_label, $reservation->notes])->filter()->implode("\n"),
                        'categories' => __('crm.calendar.reservation'),
                        'updated' => $reservation->updated_at,
                    ]);
                });
        }
    }

    private function describe($contact, ?string $details, ?string $emailSubject): string
    {
        return collect([
            $contact ? trim(($contact->name ?? '').' <'.$contact->email.'>') : null,
            $emailSubject ? __('crm.calendar.from_email', ['subject' => $emailSubject]) : null,
            $details,
        ])->filter()->implode("\n");
    }
}
