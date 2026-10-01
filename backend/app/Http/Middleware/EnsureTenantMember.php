<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRM endpoints are for restaurant staff only: the user must belong to an
 * active group. Master admins have no group and do not use the CRM.
 */
class EnsureTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isMasterAdmin() || $user->group_id === null) {
            return response()->json(['message' => __('crm.no_group'), 'code' => 'no_group'], Response::HTTP_FORBIDDEN);
        }

        if (! $user->group?->is_active) {
            return response()->json(['message' => __('crm.group_inactive'), 'code' => 'group_inactive'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
