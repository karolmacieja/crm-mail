<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Master Admin: restaurants ("Grupy").
 */
class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $groups = Group::query()
            ->withCount(['users', 'contacts'])
            ->with(['licenses' => fn ($q) => $q->withCount('users')->orderByDesc('expires_at')])
            ->when($validated['search'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('contact_email', 'like', $like));
            })
            ->when(isset($validated['is_active']), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return GroupResource::collection($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $group = Group::create($this->validated($request));

        return (new GroupResource($this->load($group)))->response()->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        return new GroupResource($this->load($group));
    }

    public function update(Request $request, Group $group): GroupResource
    {
        $group->update($this->validated($request, $group));

        return new GroupResource($this->load($group));
    }

    /**
     * Irreversible: removes the restaurant with all its contacts, reservations,
     * timeline, licenses and staff accounts. Requires ?confirm=<slug>.
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        if ($request->query('confirm') !== $group->slug) {
            throw ValidationException::withMessages(['confirm' => [__('crm.group_delete_confirm', ['slug' => $group->slug])]]);
        }

        DB::transaction(function () use ($group) {
            $group->users()->each(function (User $user) {
                $user->tokens()->delete();
                $user->delete();
            });
            $group->delete(); // cascades to every tenant table
        });

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Group $group = null): array
    {
        $required = $group === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:80', 'alpha_dash', Rule::unique('groups', 'slug')->ignore($group)],
            'timezone' => ['sometimes', 'timezone:all'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function load(Group $group): Group
    {
        return $group->loadCount(['users', 'contacts'])->load([
            'users' => fn ($q) => $q->with(['group', 'license'])->orderBy('name'),
            'licenses' => fn ($q) => $q->withCount('users')->orderByDesc('expires_at'),
        ]);
    }
}
