<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\ContactAccessRequest;
use App\Models\User;
use App\Support\Sharing\ContactAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Contacts and clients of the current restaurant. GroupScope limits every
 * query (and route-model binding) to the user's group, so other restaurants'
 * records simply do not exist from here (404). Inside the restaurant each
 * contact belongs to its owner; colleagues see it only when it was shared
 * with them (ContactAccess), otherwise it is a 404 as well.
 */
class ContactController extends Controller
{
    private const SORTABLE = ['name', 'email', 'company', 'status', 'created_at', 'updated_at', 'last_activity_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:60'],   // id or slug
            'is_client' => ['nullable', 'boolean'],          // "Klienci" (1) vs "Kontakty" (0)
            'owner' => ['nullable', Rule::in(['mine', 'shared'])], // own contacts / shared with me
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $contacts = Contact::query()
            ->visibleTo($user)
            ->with(['category', 'latestActivity', 'owner:id,name', 'viewerShares' => fn ($q) => $q->forViewer($user)])
            ->withCount(['openTasks' => fn ($q) => $q->visibleTo($user)])
            ->when(($validated['owner'] ?? null) === 'mine', fn (Builder $q) => $q->ownedBy($user))
            ->when(($validated['owner'] ?? null) === 'shared', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id)))
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
     * A colleague's private contact also returns `data: null`, plus who looks
     * after the client so the sidebar can offer "Poproś o dostęp".
     */
    public function lookup(Request $request): JsonResponse|ContactResource
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = $request->user();
        $cards = Contact::query()->with(['owner:id,name', 'category'])->where('email', mb_strtolower(trim($validated['email'])))->get();

        // My personal card first, then the shared card (there is at most one of each).
        $contact = $cards->first(fn (Contact $c) => $c->isPersonal() && $c->user_id === $user->id)
            ?? $cards->first(fn (Contact $c) => ! $c->isPersonal());

        if ($contact === null) {
            $personal = $cards->first();

            // Business correspondence kept by colleagues: I can start my own card and see the team pool.
            return response()->json($personal === null ? ['data' => null] : ['data' => null, 'meta' => ['personal_cards' => [
                'category' => $personal->category ? ['id' => $personal->category->id, 'name' => $personal->category->name] : null,
                'team_emails' => Activity::query()->teamPool()->whereIn('contact_id', $cards->modelKeys())->count(),
            ]]]);
        }

        if (! $contact->accessFor($request->user())->canView) {
            $pending = $contact->accessRequests()
                ->where('user_id', $request->user()->id)
                ->where('status', ContactAccessRequest::PENDING)
                ->exists();

            return response()->json(['data' => null, 'meta' => ['owned_by_colleague' => [
                'contact_id' => $contact->id,
                'owner' => ['id' => $contact->owner->id, 'name' => $contact->owner->name],
                'access_requested' => $pending,
            ]]]);
        }

        return new ContactResource($this->loadProfile($contact, $request));
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
            Contact::query()->visibleTo($request->user())->with('category')->whereIn('email', $emails)->get()
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
        self::authorizeView($request->user(), $contact);

        return new ContactResource($this->loadProfile($contact, $request));
    }

    /** Only the owner changes the contact's data; colleagues add their own entries. */
    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $user = $request->user();
        self::authorizeManage($user, $contact);
        $wasPersonal = $contact->isPersonal();

        // A contact without an owner moved to a private category becomes my personal card.
        if ($contact->user_id === null && Contact::isPrivateCategory($request->validated('category_id', $contact->category_id))) {
            $contact->user_id = $user->id;
        }
        $contact->update($request->validated());

        // Business correspondence is private: drop sharing set up before.
        if (! $wasPersonal && $contact->isPersonal()) {
            $contact->shares()->delete();
            $contact->accessRequests()->where('status', ContactAccessRequest::PENDING)
                ->update(['status' => ContactAccessRequest::DECLINED, 'decided_at' => now()]);
        }

        return new ContactResource($this->loadProfile($contact, $request));
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        self::authorizeManage($request->user(), $contact);
        $contact->delete();

        return response()->json(null, 204);
    }

    /**
     * Everything the right sidebar / client card shows except the timeline,
     * which is paginated separately (GET /contacts/{id}/activities).
     */
    private function loadProfile(Contact $contact, Request $request): Contact
    {
        $user = $request->user();
        $contact->forgetAccess();
        $access = $contact->accessFor($user);

        $contact->load([
            'category',
            'owner:id,name',
            'customFields',
            'latestActivity',
            'recentNotes' => fn ($q) => $q->visibleWith($access, $user)->with('author:id,name')->limit(3),
            'tasks' => fn ($q) => $q->visibleTo($user)->with(['assignee:id,name', 'calendarEvents' => $this->myCalendarEvents($request)])->orderBy('is_completed')->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'reminders' => fn ($q) => $q->visibleTo($user)->with(['calendarEvents' => $this->myCalendarEvents($request)])->pending()->orderBy('remind_at'),
            'upcomingReservations' => fn ($q) => $q->visibleTo($user)->upcoming($this->timezone($request)),
        ])->loadCount([
            'openTasks' => fn ($q) => $q->visibleTo($user),
            'notes' => fn ($q) => $q->visibleWith($access, $user),
        ]);

        if ($access->canManage) {
            $contact->load(['shares.user:id,name', 'accessRequests' => fn ($q) => $q->where('status', ContactAccessRequest::PENDING)->with('requester:id,name')]);
        }

        return $contact;
    }

    /** A colleague's private contact does not exist for you (404). */
    public static function authorizeView(User $user, Contact $contact): ContactAccess
    {
        $access = $contact->accessFor($user);
        abort_unless($access->canView, 404);

        return $access;
    }

    /** Changing the contact itself and its sharing is up to its owner. */
    public static function authorizeManage(User $user, Contact $contact): ContactAccess
    {
        $access = self::authorizeView($user, $contact);
        abort_unless($access->canManage, 403, __('crm.sharing.owner_only'));

        return $access;
    }

    private function myCalendarEvents(Request $request): \Closure
    {
        return fn ($q) => $q->where('user_id', $request->user()->id);
    }
}
