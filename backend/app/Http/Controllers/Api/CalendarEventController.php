<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Remembers which Google Calendar event the extension created for a task or
 * reminder in the signed-in person's calendar, so later edits update that
 * event instead of creating another one.
 */
class CalendarEventController extends Controller
{
    /** PUT /calendar-events/{type}/{id}  { external_id } */
    public function upsert(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate(['external_id' => ['required', 'string', 'max:1024']]);
        $item = $this->find($request, $type, $id);

        $item->calendarEvents()->updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => 'google'],
            ['external_id' => $data['external_id']],
        );

        return response()->json(['data' => ['type' => $type, 'id' => $id, 'external_id' => $data['external_id']]]);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $this->find($request, $type, $id)->calendarEvents()->where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }

    /** GroupScope keeps other restaurants' records out of reach (404). */
    private function find(Request $request, string $type, int $id): Model
    {
        return match ($type) {
            'task' => Task::query()->visibleTo($request->user())->findOrFail($id),
            'reminder' => Reminder::query()->visibleTo($request->user())->findOrFail($id),
            default => abort(404),
        };
    }
}
