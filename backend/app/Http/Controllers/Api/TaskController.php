<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'completed', 'overdue', 'all'])],
            'contact_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'open';

        $tasks = $request->user()->tasks()
            ->with('contact:id,email,name')
            ->when($validated['contact_id'] ?? null, fn ($q, $id) => $q->where('contact_id', $id))
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'completed', fn ($q) => $q->where('is_completed', true))
            ->when($status === 'overdue', fn ($q) => $q->overdue())
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

        return (new TaskResource($task->load('contact:id,email,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $task): TaskResource
    {
        return new TaskResource($this->findOwned($request, $task)->load('contact:id,email,name'));
    }

    public function update(UpdateTaskRequest $request, int $task): TaskResource
    {
        $model = $this->findOwned($request, $task);
        $model->update($request->validated());

        return new TaskResource($model->load('contact:id,email,name'));
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        $this->findOwned($request, $task)->delete();

        return response()->json(null, 204);
    }

    private function findOwned(Request $request, int $id): Task
    {
        return $request->user()->tasks()->findOrFail($id);
    }
}
