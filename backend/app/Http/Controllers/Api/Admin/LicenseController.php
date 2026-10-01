<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LicenseResource;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Master Admin: licenses and seat assignments ("Licencje i Przydziały").
 * Reachable only through the web panel session — never from the extension.
 */
class LicenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'group_id' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::in(['active', 'expired', 'expiring'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $licenses = License::query()
            ->with('group')
            ->withCount('users')
            ->when($validated['group_id'] ?? null, fn ($q, $id) => $q->where('group_id', $id))
            ->when(($validated['state'] ?? null) === 'active', fn ($q) => $q->active())
            ->when(($validated['state'] ?? null) === 'expired', fn ($q) => $q->where('expires_at', '<=', now()))
            ->when(($validated['state'] ?? null) === 'expiring', fn ($q) => $q->active()->where('expires_at', '<=', now()->addDays(30)))
            ->orderBy('expires_at')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return LicenseResource::collection($licenses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'plan' => ['sometimes', 'string', 'max:30'],
            'seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            // Either an explicit end date or a duration.
            'expires_at' => ['required_without:days', 'date', 'after:starts_at'],
            'days' => ['required_without:expires_at', 'integer', 'min:1', 'max:3650'],
            'status' => ['sometimes', Rule::enum(LicenseStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $startsAt = isset($data['starts_at']) ? now()->parse($data['starts_at']) : now();
        $license = Group::findOrFail($data['group_id'])->licenses()->create([
            'plan' => $data['plan'] ?? 'standard',
            'seats' => $data['seats'],
            'starts_at' => $startsAt,
            'expires_at' => $data['expires_at'] ?? $startsAt->copy()->addDays($data['days']),
            'status' => $data['status'] ?? LicenseStatus::Active->value,
            'notes' => $data['notes'] ?? null,
        ]);

        return (new LicenseResource($this->load($license)))->response()->setStatusCode(201);
    }

    public function show(License $license): LicenseResource
    {
        return new LicenseResource($this->load($license));
    }

    public function update(Request $request, License $license): LicenseResource
    {
        $data = $request->validate([
            'plan' => ['sometimes', 'string', 'max:30'],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::enum(LicenseStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'regenerate_key' => ['sometimes', 'boolean'],
        ]);

        $used = $license->seatsUsed();
        if (isset($data['seats']) && $data['seats'] < $used) {
            throw ValidationException::withMessages(['seats' => [__('crm.license_seats_exceeded', ['used' => $used])]]);
        }

        $license->fill(collect($data)->except('regenerate_key')->all());
        if ($request->boolean('regenerate_key')) {
            $license->key = License::generateKey();
        }
        $license->save();

        return new LicenseResource($this->load($license));
    }

    public function destroy(License $license): JsonResponse
    {
        $license->delete(); // seats (license_user) cascade

        return response()->json(null, 204);
    }

    /** Extend by N days (from the current expiry while still active). */
    public function extend(Request $request, License $license): LicenseResource
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);

        $license->extend($data['days']);

        return new LicenseResource($this->load($license));
    }

    /** Give a user (of the license's group) a seat. */
    public function assign(Request $request, License $license): LicenseResource
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        try {
            $license->assignTo(User::findOrFail($data['user_id']), $request->user());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['user_id' => [$e->getMessage()]]);
        }

        return new LicenseResource($this->load($license));
    }

    public function unassign(License $license, User $user): LicenseResource
    {
        $license->unassign($user);

        return new LicenseResource($this->load($license));
    }

    private function load(License $license): License
    {
        return $license->load(['group', 'users'])->loadCount('users');
    }
}
