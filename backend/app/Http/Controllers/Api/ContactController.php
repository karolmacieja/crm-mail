<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    private const SORTABLE = ['name', 'email', 'status', 'created_at', 'updated_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // GroupScope limits every query to the user's restaurant.
        $contacts = Contact::query()
            ->search($validated['search'] ?? null)
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->withCount('openTasks')
            ->orderBy($validated['sort'] ?? 'updated_at', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    /**
     * Find a contact by email address (used by the Gmail sidebar).
     * Returns `{"data": null}` instead of 404 because "not in CRM yet" is an
     * expected state for the sidebar, not an error.
     */
    public function lookup(Request $request): JsonResponse|ContactResource
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $contact = Contact::query()
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->withCount('openTasks')
            ->with(['tasks' => fn ($q) => $q->orderBy('is_completed')->orderByRaw('due_date IS NULL')->orderBy('due_date')])
            ->first();

        if ($contact === null) {
            return response()->json(['data' => null]);
        }

        return new ContactResource($contact);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $request->user()->contacts()->create($request->validated());
        $contact->loadCount('openTasks')->setRelation('tasks', collect());

        return (new ContactResource($contact))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $contact): ContactResource
    {
        $model = $this->findOwned($request, $contact)
            ->loadCount('openTasks')
            ->load(['tasks' => fn ($q) => $q->orderBy('is_completed')->orderByRaw('due_date IS NULL')->orderBy('due_date')]);

        return new ContactResource($model);
    }

    public function update(UpdateContactRequest $request, int $contact): ContactResource
    {
        $model = $this->findOwned($request, $contact);
        $model->update($request->validated());

        return new ContactResource($model->loadCount('openTasks'));
    }

    public function destroy(Request $request, int $contact): JsonResponse
    {
        $this->findOwned($request, $contact)->delete();

        return response()->json(null, 204);
    }

    /**
     * GroupScope makes other restaurants' records indistinguishable
     * from non-existent ones (404, not 403).
     */
    private function findOwned(Request $request, int $id): Contact
    {
        return Contact::findOrFail($id);
    }
}
