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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'contact_id' => ['nullable', 'integer'],
            'upcoming' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $reservations = Reservation::query()
            ->visibleTo($request->user())
            ->with('contact:id,email,name,user_id')
            ->when($request->boolean('upcoming'), fn (Builder $q) => $q->upcoming($this->timezone($request)))
            ->when($validated['from'] ?? null, fn (Builder $q, $from) => $q->where('reservation_date', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $q, $to) => $q->where('reservation_date', '<=', $to))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($validated['contact_id'] ?? null, fn (Builder $q, $id) => $q->where('contact_id', $id))
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

        return (new ReservationResource($reservation->load('contact:id,email,name,user_id')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Reservation $reservation): ReservationResource
    {
        $this->authorizeItem($request, $reservation, view: true);

        return new ReservationResource($reservation->load('contact:id,email,name,user_id'));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): ReservationResource
    {
        $this->authorizeItem($request, $reservation);
        $reservation->update($request->validated());

        return new ReservationResource($reservation->load('contact:id,email,name,user_id'));
    }

    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeItem($request, $reservation);
        $reservation->delete();

        return response()->json(null, 204);
    }
}
