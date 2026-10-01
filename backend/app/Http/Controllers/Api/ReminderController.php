<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReminderType;
use App\Http\Controllers\Api\Concerns\FiltersByDueWindow;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReminderRequest;
use App\Http\Requests\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * "Kalendarz Przypomnień": reminders from emails, about reservations, or general.
 */
class ReminderController extends Controller
{
    use FiltersByDueWindow;

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'window' => ['nullable', Rule::in(self::WINDOWS)],
            'type' => ['nullable', Rule::enum(ReminderType::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer'],
            'reservation_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Reminder::query()->with(['contact:id,email,name', 'reservation']);
        $this->applyWindow($query, $validated['window'] ?? 'open', $this->timezone($request));

        $reminders = $query
            ->when($validated['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($validated['contact_id'] ?? null, fn (Builder $q, $id) => $q->where('contact_id', $id))
            ->when($validated['reservation_id'] ?? null, fn (Builder $q, $id) => $q->where('reservation_id', $id))
            ->when($validated['search'] ?? null, function (Builder $q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('source_email_subject', 'like', $like));
            })
            ->orderBy('is_done')
            ->orderBy('remind_at')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ReminderResource::collection($reminders);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = new Reminder($request->validated());
        $reminder->user_id = $request->user()->id;
        $reminder->save();

        return (new ReminderResource($reminder->load(['contact:id,email,name', 'reservation'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Reminder $reminder): ReminderResource
    {
        return new ReminderResource($reminder->load(['contact:id,email,name', 'reservation']));
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): ReminderResource
    {
        $reminder->update($request->validated());

        return new ReminderResource($reminder->load(['contact:id,email,name', 'reservation']));
    }

    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json(null, 204);
    }
}
