<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isMasterAdmin()) {
            return response()->json(['message' => __('crm.admin_only')], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
