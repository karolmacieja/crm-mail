<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks CRM endpoints for users without an active, unexpired license.
 *
 * Responds with HTTP 402 (Payment Required) and a machine-readable `code`
 * so the extension can distinguish license problems from auth (401) and
 * authorization (403) errors and show an upgrade/renew prompt.
 */
class EnsureValidLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->is_admin || $user->hasValidLicense()) {
            return $next($request);
        }

        $status = $user->licenseStatus();

        return response()->json([
            'message' => $status === 'expired'
                ? 'Your license expired on '.$user->license_expires_at->toDateString().'. Please renew to continue.'
                : 'No active license found for this account.',
            'code' => $status === 'expired' ? 'license_expired' : 'license_missing',
            'license_expires_at' => $user->license_expires_at?->toIso8601String(),
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
