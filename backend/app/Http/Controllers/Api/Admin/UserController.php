<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\License;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Master Admin: user accounts. Only the Master Admin manages users;
 * restaurant staff (manager/staff) have no management rights.
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with(['group', 'license'])
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->when($validated['group_id'] ?? null, fn ($q, $id) => $q->where('group_id', $id))
            ->when($validated['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Create an account; optionally give it a seat on a license right away.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
            ]);
            $user->role = UserRole::from($data['role']);
            $user->group_id = $data['group_id'] ?? null;
            $user->email_verified_at = now();
            $user->save();

            if (! empty($data['license_id'])) {
                $this->assignSeat($user, (int) $data['license_id'], $request->user());
            }

            return $user;
        });

        return (new UserResource($user->load(['group', 'license'])))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['group', 'license']));
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($user, $data) {
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'password'])));
            if (isset($data['email'])) {
                $user->email = mb_strtolower($data['email']);
            }
            if (isset($data['role'])) {
                $user->role = UserRole::from($data['role']);
            }

            $newGroupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
            $groupChanged = array_key_exists('group_id', $data) && $newGroupId !== $user->group_id;
            if (array_key_exists('group_id', $data)) {
                $user->group_id = $newGroupId;
            }

            if ($user->role === UserRole::MasterAdmin) {
                $user->group_id = null;
            }

            $user->save();

            // Moving to another restaurant: drop the old seat and sign out of the extension.
            if ($groupChanged || $user->role === UserRole::MasterAdmin) {
                $user->revokeLicense();
                $user->tokens()->delete();
            }

            if (isset($data['password'])) {
                $user->tokens()->delete();
            }
        });

        return new UserResource($user->fresh(['group', 'license']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => [__('crm.cannot_delete_self')]]);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete(); // their contacts/tasks stay with the restaurant (user_id → NULL)
        });

        return response()->json(null, 204);
    }

    /** Sign the user out of every Gmail extension installation. */
    public function revokeTokens(User $user): JsonResponse
    {
        $count = $user->tokens()->delete();

        return response()->json(['revoked' => $count]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $required = $user === null ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$required, 'string', Password::min(8)],
            'role' => [$required, Rule::enum(UserRole::class)],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'license_id' => ['nullable', 'integer', 'exists:licenses,id'],
        ]);

        $role = $data['role'] ?? $user?->role->value;
        $groupId = array_key_exists('group_id', $data) ? $data['group_id'] : $user?->group_id;

        if ($role === UserRole::MasterAdmin->value && $groupId !== null && array_key_exists('group_id', $data)) {
            throw ValidationException::withMessages(['group_id' => [__('crm.master_admin_no_group')]]);
        }

        if ($role !== UserRole::MasterAdmin->value && $groupId === null) {
            throw ValidationException::withMessages(['group_id' => [__('crm.user_needs_group')]]);
        }

        return $data;
    }

    private function assignSeat(User $user, int $licenseId, User $admin): void
    {
        try {
            License::findOrFail($licenseId)->assignTo($user, $admin);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['license_id' => [$e->getMessage()]]);
        }
    }
}
