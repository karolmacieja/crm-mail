<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Colleagues from the same restaurant (task assignment picker). Read-only:
 * staff accounts are managed exclusively by the Master Admin.
 */
class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = User::query()
            ->where('group_id', $request->user()->group_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json([
            'data' => $members->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_me' => $user->is($request->user()),
            ]),
        ]);
    }
}
