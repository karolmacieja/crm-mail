<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            // overdue (open, past days) | today | upcoming (next 7 days) | open (today and later) | done | all
            'window' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'open', 'done', 'all'])],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'contact_id' => ['nullable', 'integer'],
            'upcoming' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tz = $this->timezone($request);
        $window = $validated['window'] ?? null;

        $reservations = Reservation::query()
            ->visibleTo($request->user())
            ->with($this->relations($request))
            ->when($request->boolean('upcoming') || $window === 'open', fn (Builder $q) => $q->upcoming($tz))
            ->when($window === 'overdue', fn (Builder $q) => $q->overdue($tz))
            ->when($window === 'today', fn (Builder $q) => $q->dueToday($tz))
            ->when($window === 'upcoming', fn (Builder $q) => $q->upcomingWeek($tz))
            ->when($window === 'done', fn (Builder $q) => $q->whereNotIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed]))
            ->when($validated['search'] ?? null, function (Builder $q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn (Builder $q) => $q->where('occasion', 'like', $like)->orWhere('table_label', 'like', $like)
                    ->orWhereHas('contact', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($validated['from'] ?? null, fn (Builder $q, $from) => $q->where('reservation_date', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $q, $to) => $q->where('reservation_date', '<=', $to))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($validated['contact_id'] ?? null, fn (Builder $q, $id) => $q->where('contact_id', $id))
            ->when($window === 'done', fn (Builder $q) => $q->orderByDesc('reservation_date')->orderByDesc('reservation_time'))
            ->orderBy('reservation_date')
            ->orderBy('reservation_time')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $reservation = new Reservation($request->validated());
        $reservation->user_id = $request->user()->id;
        $reservation->save();

        return (new ReservationResource($reservation->load($this->relations($request))))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Reservation $reservation): ReservationResource
    {
        $this->authorizeItem($request, $reservation, view: true);

        return new ReservationResource($reservation->load($this->relations($request)));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): ReservationResource
    {
        $this->authorizeItem($request, $reservation);
        $reservation->update($request->validated());

        return new ReservationResource($reservation->load($this->relations($request)));
    }

    /** @return array<int|string, mixed> */
    private function relations(Request $request): array
    {
        return [
            'contact:id,email,name,user_id',
            'autoReminder' => fn ($q) => $q->with(['contact:id,email,name,user_id', 'calendarEvents' => fn ($q) => $q->where('user_id', $request->user()->id)]),
            'calendarEvents' => fn ($q) => $q->where('user_id', $request->user()->id),
        ];
    }

    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeItem($request, $reservation);
        $reservation->delete();

        return response()->json(null, 204);
    }
}
