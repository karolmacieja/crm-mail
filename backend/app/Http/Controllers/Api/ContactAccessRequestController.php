<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactAccessRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Poproś o dostęp": asking a colleague to share their client, and the
 * owner's list of requests to approve (choosing what to share) or decline.
 */
class ContactAccessRequestController extends Controller
{
    /** Pending requests for my contacts, and my own pending requests. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $present = fn (ContactAccessRequest $r) => [
            'id' => $r->id,
            'status' => $r->status,
            'message' => $r->message,
            'contact' => ['id' => $r->contact->id, 'name' => $r->contact->name, 'email' => $r->contact->email],
            'user' => ['id' => $r->requester->id, 'name' => $r->requester->name],
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        $pending = fn () => ContactAccessRequest::query()->where('status', ContactAccessRequest::PENDING)
            ->with(['contact:id,name,email,user_id', 'requester:id,name'])->latest();

        return response()->json(['data' => [
            'incoming' => $pending()->whereHas('contact', fn (Builder $q) => $q->ownedBy($user))->get()->map($present),
            'outgoing' => $pending()->where('user_id', $user->id)->get()->map($present),
        ]]);
    }

    public function store(Request $request, Contact $contact): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['message' => ['nullable', 'string', 'max:500']]);

        ContactShareController::refusePersonal($contact);
        abort_if($contact->accessFor($user)->isFull(), 422, __('crm.sharing.already_has_access'));

        $accessRequest = ContactAccessRequest::query()->firstOrNew([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'status' => ContactAccessRequest::PENDING,
        ]);
        $accessRequest->message = $data['message'] ?? $accessRequest->message;
        $accessRequest->group_id = $contact->group_id;
        $accessRequest->save();

        return response()->json(['data' => ['id' => $accessRequest->id, 'status' => $accessRequest->status]], $accessRequest->wasRecentlyCreated ? 201 : 200);
    }

    /** Body: { scopes: string[], email_ids?: int[] } – what the colleague gets. */
    public function approve(Request $request, ContactAccessRequest $accessRequest): JsonResponse
    {
        $contact = $this->authorizeOwner($request, $accessRequest);
        $data = ContactShareController::validateScopes($request, $contact);

        ContactShareController::saveShare($request, $contact, $data + ['user_id' => $accessRequest->user_id]);

        return response()->json(['data' => ['id' => $accessRequest->id, 'status' => $accessRequest->fresh()->status]]);
    }

    public function decline(Request $request, ContactAccessRequest $accessRequest): JsonResponse
    {
        $this->authorizeOwner($request, $accessRequest);
        $accessRequest->update(['status' => ContactAccessRequest::DECLINED, 'decided_at' => now()]);

        return response()->json(['data' => ['id' => $accessRequest->id, 'status' => $accessRequest->status]]);
    }

    private function authorizeOwner(Request $request, ContactAccessRequest $accessRequest): Contact
    {
        abort_unless($accessRequest->status === ContactAccessRequest::PENDING, 409, __('crm.sharing.request_closed'));
        $contact = $accessRequest->contact;
        ContactController::authorizeManage($request->user(), $contact);

        return $contact;
    }
}
