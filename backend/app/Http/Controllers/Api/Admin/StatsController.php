<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Overview for the Master Admin panel's start page.
 */
class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'groups' => [
                    'total' => Group::count(),
                    'active' => Group::where('is_active', true)->count(),
                ],
                'users' => [
                    'total' => User::where('role', '!=', UserRole::MasterAdmin)->count(),
                    'without_seat' => User::where('role', '!=', UserRole::MasterAdmin)->whereDoesntHave('license')->count(),
                ],
                'licenses' => [
                    'active' => License::active()->count(),
                    'expiring_30_days' => License::active()->where('expires_at', '<=', now()->addDays(30))->count(),
                    'expired' => License::where('expires_at', '<=', now())->count(),
                    'seats_total' => (int) License::active()->sum('seats'),
                    'seats_used' => License::active()->withCount('users')->get()->sum('users_count'),
                ],
            ],
        ]);
    }
}
