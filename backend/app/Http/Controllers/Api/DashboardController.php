<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Http\Resources\TaskResource;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Numbers and lists for the CRM dashboard. Tasks and reminders get separate
 * counters for the three time windows (overdue / today / next 7 days),
 * computed in the restaurant's timezone.
 */
class DashboardController extends Controller
{
    private const LIST_LIMIT = 20;

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Optional override; defaults to the restaurant's timezone.
            'tz' => ['nullable', 'timezone:all'],
        ]);

        $tz = $validated['tz'] ?? $this->timezone($request);
        $startOfToday = Carbon::now($tz)->startOfDay()->utc();
        $today = Carbon::now($tz)->toDateString();

        $byCategory = ContactCategory::query()->withCount('contacts')->orderBy('sort_order')->get()
            ->map(fn (ContactCategory $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'color' => $c->color, 'count' => $c->contacts_count]);

        $statusCounts = Contact::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return response()->json([
            'data' => [
                'contacts' => [
                    'total' => Contact::count(),
                    'clients' => Contact::clients()->count(),
                    'by_category' => $byCategory,
                    'by_status' => $statusCounts->map(fn ($n) => (int) $n),
                ],
                'tasks' => $this->windowSummary(
                    fn () => Task::query()->with(['contact:id,email,name', 'assignee:id,name']),
                    fn ($items) => TaskResource::collection($items),
                    $tz,
                ) + [
                    'open' => Task::query()->pending()->count(),
                    'urgent' => Task::query()->urgent($tz)->count(),
                    'completed_this_week' => Task::query()->where('is_completed', true)->where('completed_at', '>=', now()->subDays(7))->count(),
                ],
                'reminders' => $this->windowSummary(
                    fn () => Reminder::query()->with(['contact:id,email,name', 'reservation']),
                    fn ($items) => ReminderResource::collection($items),
                    $tz,
                ),
                'reservations' => [
                    'today' => Reservation::query()->upcoming($tz)->onDate($today)->count(),
                    'today_guests' => (int) Reservation::query()->upcoming($tz)->onDate($today)->sum('guests_count'),
                    'upcoming' => Reservation::query()->upcoming($tz)->count(),
                ],
                'emails' => [
                    // Emails logged to timelines today ("Nowych maili").
                    'today' => Activity::query()->where('type', ActivityType::Email)->where('occurred_at', '>=', $startOfToday)->count(),
                ],
                'timezone' => $tz,
            ],
        ]);
    }

    /**
     * @param  callable(): Builder  $query
     * @param  callable(Collection): mixed  $present
     * @return array<string, mixed>
     */
    private function windowSummary(callable $query, callable $present, string $tz): array
    {
        $order = fn (Builder $q) => $q->orderBy($q->getModel()::DUE_COLUMN)->limit(self::LIST_LIMIT)->get();

        return [
            'overdue' => $query()->overdue()->count(),
            'due_today' => $query()->dueToday($tz)->count(),
            'upcoming_week' => $query()->upcoming($tz)->count(),
            'lists' => [
                'overdue' => $present($order($query()->overdue())),
                'today' => $present($order($query()->dueToday($tz))),
                'upcoming' => $present($order($query()->upcoming($tz))),
            ],
        ];
    }
}
