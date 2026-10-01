<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Issue or extend a user's license by N days.
     */
    public function grant(Request $request, User $user): UserResource
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'regenerate_key' => ['sometimes', 'boolean'],
        ]);

        try {
            DB::transaction(fn () => $user->grantLicense(
                $validated['days'],
                (bool) ($validated['regenerate_key'] ?? false),
                $request->user(),
            ));
        } catch (DomainException $e) {
            // No group / no free seat: a client error, not a server error.
            throw ValidationException::withMessages(['user' => [$e->getMessage()]]);
        }

        return new UserResource($user->refresh());
    }

    /**
     * Expire a license immediately and log the user out of every device.
     */
    public function revoke(User $user): UserResource
    {
        DB::transaction(function () use ($user) {
            $user->revokeLicense();
            $user->tokens()->delete();
        });

        return new UserResource($user->refresh());
    }
}
