<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the two front-ends apart even though both authenticate with Sanctum:
 *
 *  client:web        – the Master Admin panel. Must be a cookie/session login
 *                      (Sanctum marks those with a TransientToken). A personal
 *                      access token from the Gmail extension is rejected, so the
 *                      extension can never reach license/user management.
 *  client:extension  – the Gmail extension. Must be a personal access token
 *                      carrying the "crm" ability.
 */
class EnsureClientType
{
    public const EXTENSION_ABILITY = 'crm';

    public function handle(Request $request, Closure $next, string $client): Response
    {
        $token = $request->user()?->currentAccessToken();
        $isSession = $token instanceof TransientToken;

        $allowed = match ($client) {
            'web' => $isSession,
            'extension' => $token !== null && ! $isSession && $token->can(self::EXTENSION_ABILITY),
            default => false,
        };

        if (! $allowed) {
            return response()->json([
                'message' => __('crm.wrong_client'),
                'code' => 'wrong_client',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
