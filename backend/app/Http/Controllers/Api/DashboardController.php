<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Aggregated numbers + reminder lists for the full-page Gmail dashboard.
     * `tz` lets "today" match the user's local day instead of UTC.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tz' => ['nullable', 'timezone:all'],
        ]);

        $user = $request->user();
        $tz = $validated['tz'] ?? config('app.timezone');
        $now = Carbon::now();
        $endOfToday = $now->copy()->setTimezone($tz)->endOfDay()->utc();
        $endOfWeek = $endOfToday->copy()->addDays(7);

        $statusCounts = $user->contacts()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(ContactStatus::values())
            ->mapWithKeys(fn (string $status) => [$status => (int) ($statusCounts[$status] ?? 0)]);

        $open = fn () => $user->tasks()->open()->with('contact:id,email,name');

        $overdue = $open()->where('due_date', '<', $now)->orderBy('due_date')->limit(20)->get();
        $today = $open()->whereBetween('due_date', [$now, $endOfToday])->orderBy('due_date')->limit(20)->get();
        $upcoming = $open()->where('due_date', '>', $endOfToday)->where('due_date', '<=', $endOfWeek)
            ->orderBy('due_date')->limit(20)->get();

        return response()->json([
            'data' => [
                'contacts' => [
                    'total' => $byStatus->sum(),
                    'by_status' => $byStatus,
                ],
                'tasks' => [
                    'open' => $user->tasks()->open()->count(),
                    'overdue' => $user->tasks()->overdue()->count(),
                    'due_today' => $user->tasks()->open()->whereBetween('due_date', [$now, $endOfToday])->count(),
                    'upcoming_week' => $user->tasks()->open()->where('due_date', '>', $endOfToday)->where('due_date', '<=', $endOfWeek)->count(),
                    'completed_this_week' => $user->tasks()->where('is_completed', true)->where('completed_at', '>=', $now->copy()->subDays(7))->count(),
                ],
                'reminders' => [
                    'overdue' => TaskResource::collection($overdue),
                    'today' => TaskResource::collection($today),
                    'upcoming' => TaskResource::collection($upcoming),
                ],
                'timezone' => $tz,
            ],
        ]);
    }
}
