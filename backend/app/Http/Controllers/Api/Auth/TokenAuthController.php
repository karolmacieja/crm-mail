<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureClientType;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login for the Gmail extension (restaurant staff): issues a Sanctum
 * personal access token with the "crm" ability, sent as a Bearer header.
 */
class TokenAuthController extends Controller
{
    /**
     * A token is issued even when the license is expired so the extension
     * can show the account and a "renew license" prompt; the CRM endpoints
     * themselves are guarded by the `license` middleware.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', mb_strtolower($request->string('email')->trim()))->first();

        if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Master admins work in the web panel only; staff need a restaurant.
        if ($user->isMasterAdmin()) {
            return response()->json(['message' => __('crm.use_web_panel'), 'code' => 'use_web_panel'], 403);
        }

        if ($user->group_id === null) {
            return response()->json(['message' => __('crm.no_group'), 'code' => 'no_group'], 403);
        }

        if (! $user->group->is_active) {
            return response()->json(['message' => __('crm.group_inactive'), 'code' => 'group_inactive'], 403);
        }

        $token = $user->createToken(
            $request->input('device_name') ?: 'gmail-extension',
            [EnsureClientType::EXTENSION_ABILITY],
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user->load(['group', 'license'])),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['group', 'license']));
    }

    /**
     * Revoke only the token used for this request (other devices stay logged in).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('crm.logged_out')]);
    }
}
