<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Contacts and clients of the current restaurant. GroupScope limits every
 * query (and route-model binding) to the user's group, so other restaurants'
 * records simply do not exist from here (404).
 */
class ContactController extends Controller
{
    private const SORTABLE = ['name', 'email', 'company', 'status', 'created_at', 'updated_at', 'last_activity_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'category' => ['nullable', 'string', 'max:60'],   // id or slug
            'is_client' => ['nullable', 'boolean'],          // "Klienci" (1) vs "Kontakty" (0)
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $contacts = Contact::query()
            ->with(['category', 'latestActivity'])
            ->withCount('openTasks')
            ->search($validated['search'] ?? null)
            ->inCategory($validated['category'] ?? null)
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when(isset($validated['is_client']), fn (Builder $q) => $q->where('is_client', $request->boolean('is_client')))
            ->orderBy($validated['sort'] ?? 'updated_at', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    /**
     * Find a contact by email (Gmail sidebar / clicked email on the dashboard).
     * Returns `{"data": null}` instead of 404: "not in CRM yet" is an expected state.
     */
    public function lookup(Request $request): JsonResponse|ContactResource
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $contact = Contact::query()->where('email', mb_strtolower(trim($validated['email'])))->first();

        return $contact === null
            ? response()->json(['data' => null])
            : new ContactResource($this->loadProfile($contact, $request));
    }

    /**
     * Batch lookup for the dashboard's inbox list: which senders are already
     * in the CRM and in which category (B2B / VIP badges). Unknown emails are omitted.
     */
    public function lookupMany(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'emails' => ['required', 'array', 'max:100'],
            'emails.*' => ['string', 'email', 'max:255'],
        ]);

        $emails = collect($validated['emails'])->map(fn (string $e) => mb_strtolower(trim($e)))->unique()->values();

        return ContactResource::collection(
            Contact::query()->with('category')->whereIn('email', $emails)->get()
        );
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = DB::transaction(function () use ($request) {
            $contact = $request->user()->contacts()->create($request->safe()->except('custom_fields'));

            foreach ($request->validated('custom_fields', []) as $index => $field) {
                $contact->customFields()->create($field + ['sort_order' => $index]);
            }

            return $contact;
        });

        return (new ContactResource($this->loadProfile($contact, $request)))->response()->setStatusCode(201);
    }

    /** Full client card ("FullClientProfile"). */
    public function show(Request $request, Contact $contact): ContactResource
    {
        return new ContactResource($this->loadProfile($contact, $request));
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($request->validated());

        return new ContactResource($this->loadProfile($contact, $request));
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(null, 204);
    }

    /**
     * Everything the right sidebar / client card shows except the timeline,
     * which is paginated separately (GET /contacts/{id}/activities).
     */
    private function loadProfile(Contact $contact, Request $request): Contact
    {
        return $contact->load([
            'category',
            'customFields',
            'latestActivity',
            'tasks' => fn ($q) => $q->with('assignee:id,name')->orderBy('is_completed')->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'reminders' => fn ($q) => $q->pending()->orderBy('remind_at'),
            'upcomingReservations' => fn ($q) => $q->upcoming($this->timezone($request)),
        ])->loadCount('openTasks');
    }
}
