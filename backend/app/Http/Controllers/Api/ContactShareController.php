<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactShareResource;
use App\Models\Contact;
use App\Models\ContactAccessRequest;
use App\Models\ContactShare;
use App\Support\Sharing\ContactAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Udostępnij": the owner shares a contact with the whole team or chosen
 * colleagues, choosing what each share covers, or hands the client over.
 */
class ContactShareController extends Controller
{
    public function index(Request $request, Contact $contact): AnonymousResourceCollection
    {
        ContactController::authorizeManage($request->user(), $contact);

        return ContactShareResource::collection($contact->shares()->with('user:id,name')->orderByRaw('user_id IS NOT NULL')->orderBy('id')->get());
    }

    /**
     * Create or replace the share for the team (user_id = null) or one colleague.
     * Body: { user_id?: int|null, scopes: string[], email_ids?: int[] }
     */
    public function store(Request $request, Contact $contact): JsonResponse
    {
        ContactController::authorizeManage($request->user(), $contact);
        $share = self::saveShare($request, $contact, $this->validated($request, $contact));

        return (new ContactShareResource($share->load('user:id,name')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Contact $contact, ContactShare $share): JsonResponse
    {
        ContactController::authorizeManage($request->user(), $contact);
        abort_unless($share->contact_id === $contact->id, 404);
        $share->delete();

        return response()->json(null, 204);
    }

    /**
     * "Przekaż opiekę": another colleague becomes the owner. By default the
     * previous owner keeps full access through a personal share.
     */
    public function transfer(Request $request, Contact $contact): JsonResponse
    {
        $user = $request->user();
        ContactController::authorizeManage($user, $contact);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('group_id', $user->group_id)],
            'keep_access' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($contact, $data, $request, $user) {
            $previous = $contact->user_id;
            $contact->forceFill(['user_id' => $data['user_id']])->save();
            $contact->shares()->where('user_id', $data['user_id'])->delete();

            if ($previous !== null && $previous !== $data['user_id'] && $request->boolean('keep_access', true)) {
                $share = $contact->shares()->firstOrNew(['user_id' => $previous]);
                $share->fill(array_fill_keys(ContactAccess::SCOPES, true) + ['activity_ids' => null]);
                $share->group_id = $contact->group_id;
                $share->created_by = $user->id;
                $share->save();
            }
        });

        $contact->forgetAccess();

        return response()->json(['data' => [
            'owner' => ['id' => $contact->owner->id, 'name' => $contact->owner->name],
        ]]);
    }

    /**
     * @return array{user_id: int|null, scopes: list<string>, email_ids: list<int>}
     */
    private function validated(Request $request, Contact $contact): array
    {
        $data = self::validateScopes($request, $contact);
        $data += $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $request->user()->group_id)],
        ]);

        if (($data['user_id'] ?? null) !== null && $data['user_id'] === $contact->user_id) {
            throw ValidationException::withMessages(['user_id' => [__('crm.sharing.already_owner')]]);
        }

        return $data + ['user_id' => null];
    }

    /**
     * @return array{scopes: list<string>, email_ids: list<int>}
     */
    public static function validateScopes(Request $request, Contact $contact): array
    {
        $data = $request->validate([
            'scopes' => ['present', 'array'],
            'scopes.*' => ['string', Rule::in(ContactAccess::SCOPES)],
            'email_ids' => ['sometimes', 'array', 'max:200'],
            'email_ids.*' => ['integer'],
        ]);

        // Only this contact's logged emails can be shared one by one.
        $ids = collect($data['email_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isNotEmpty()) {
            $valid = $contact->timeline()->where('type', ActivityType::Email)->whereIn('id', $ids)->pluck('id');
            if ($valid->count() !== $ids->count()) {
                throw ValidationException::withMessages(['email_ids' => [__('validation.exists', ['attribute' => 'email_ids'])]]);
            }
        }

        if ($data['scopes'] === [] && $ids->isEmpty()) {
            throw ValidationException::withMessages(['scopes' => [__('crm.sharing.nothing_selected')]]);
        }

        return ['scopes' => array_values(array_unique($data['scopes'])), 'email_ids' => $ids->all()];
    }

    /**
     * @param  array{user_id: int|null, scopes: list<string>, email_ids: list<int>}  $data
     */
    public static function saveShare(Request $request, Contact $contact, array $data): ContactShare
    {
        return DB::transaction(function () use ($request, $contact, $data) {
            $share = $contact->shares()->firstOrNew(['user_id' => $data['user_id']]);
            foreach (ContactAccess::SCOPES as $scope) {
                $share->{$scope} = in_array($scope, $data['scopes'], true);
            }
            $share->activity_ids = $data['email_ids'] === [] ? null : $data['email_ids'];
            $share->group_id = $contact->group_id;
            $share->created_by ??= $request->user()->id;
            $share->save();

            // Sharing answers pending requests of the people it now covers.
            $contact->accessRequests()->where('status', ContactAccessRequest::PENDING)
                ->when($data['user_id'] !== null, fn ($q) => $q->where('user_id', $data['user_id']))
                ->update(['status' => ContactAccessRequest::APPROVED, 'decided_at' => now()]);

            $contact->forgetAccess();

            return $share;
        });
    }
}
