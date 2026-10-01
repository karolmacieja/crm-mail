<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Http\Controllers\Api\Concerns\FiltersByDueWindow;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    use FiltersByDueWindow;

    /**
     * Filters used by "Moduł Zadań": window (overdue/today/upcoming/open/done/all),
     * type (follow_up/offer/internal), urgent=1 (the "Pilne" column), assignee, search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            // `status` is the pre-GastroFlowx name of `window`, still used by the extension.
            'window' => ['nullable', Rule::in(self::WINDOWS)],
            'status' => ['nullable', Rule::in(self::WINDOWS)],
            'type' => ['nullable', Rule::enum(TaskType::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'urgent' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'string', 'max:20'], // user id or "me"
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $timezone = $this->timezone($request);
        $assignee = ($validated['assigned_to'] ?? null) === 'me' ? $request->user()->id : ($validated['assigned_to'] ?? null);

        $query = Task::query()->with(['contact:id,email,name', 'assignee:id,name']);
        $this->applyWindow($query, $validated['window'] ?? $validated['status'] ?? 'open', $timezone);

        $tasks = $query
            ->when($validated['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($validated['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($request->boolean('urgent'), fn (Builder $q) => $q->urgent($timezone))
            ->when($validated['contact_id'] ?? null, fn (Builder $q, $id) => $q->where('contact_id', $id))
            ->when($assignee, fn (Builder $q, $id) => $q->where('assigned_to', $id))
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where('title', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->orderBy('is_completed')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->user()->tasks()->create($request->validated());

        return (new TaskResource($task->load(['contact:id,email,name', 'assignee:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load(['contact:id,email,name', 'assignee:id,name']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load(['contact:id,email,name', 'assignee:id,name']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(null, 204);
    }
}
