<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'default_task_due_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'calendar' => ['sometimes', 'array:alarm_minutes,include_reservations'],
            'calendar.alarm_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'calendar.include_reservations' => ['sometimes', 'boolean'],
            'google' => ['sometimes', 'array:calendar_sync,email_history'],
            'google.calendar_sync' => ['sometimes', 'boolean'],
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
