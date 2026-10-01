<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login for the Master Admin web panel (Sanctum SPA authentication):
 *   1. GET  /sanctum/csrf-cookie   (sets XSRF-TOKEN cookie)
 *   2. POST /api/web/login         (with X-XSRF-TOKEN header) → session cookie
 *   3. subsequent requests are authenticated by the session cookie.
 * The panel must be served from a domain listed in SANCTUM_STATEFUL_DOMAINS.
 */
class SessionAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->validate(['remember' => ['sometimes', 'boolean']]);

        $user = User::where('email', mb_strtolower($request->string('email')->trim()))->first();

        if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->isMasterAdmin()) {
            return response()->json(['message' => __('crm.web_panel_admins_only'), 'code' => 'web_panel_admins_only'], 403);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate(); // prevent session fixation

        return response()->json(['user' => new UserResource($user)]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('crm.logged_out')]);
    }
}
