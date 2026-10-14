<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Personal settings of the signed-in staff member. */
class PreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_reminder_time' => ['sometimes', 'date_format:H:i'],
            'default_reminder_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'default_task_due_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'default_task_time' => ['sometimes', 'date_format:H:i'],
            'default_task_priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'reservations' => ['sometimes', 'array:default_time,default_guests,duration_minutes,auto_reminder,reminder_hours_before'],
            'reservations.default_time' => ['sometimes', 'date_format:H:i'],
            'reservations.default_guests' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'reservations.duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:1440'],
            'reservations.auto_reminder' => ['sometimes', 'boolean'],
            'reservations.reminder_hours_before' => ['sometimes', 'integer', 'min:0', 'max:336'],
            'calendar' => ['sometimes', 'array:alarm_minutes,include_tasks,include_reminders,include_reservations'],
            'calendar.alarm_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'calendar.include_tasks' => ['sometimes', 'boolean'],
            'calendar.include_reminders' => ['sometimes', 'boolean'],
            'calendar.include_reservations' => ['sometimes', 'boolean'],
            'google' => ['sometimes', 'array:calendar_sync,calendar_sync_reservations,email_history'],
            'google.calendar_sync' => ['sometimes', 'boolean'],
            'google.calendar_sync_reservations' => ['sometimes', 'boolean'],
            'google.email_history' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->preferences = array_replace_recursive($user->preferences ?? [], $data);
        $user->save();

        return response()->json(['data' => $this->present($request)]);
    }

    /** @return array<string, mixed> */
    private function present(Request $request): array
    {
        $user = $request->user();

        return $user->preferences() + [
            'calendar_feed_url' => CalendarFeedController::urlFor($user->calendar_token),
        ];
    }
}
