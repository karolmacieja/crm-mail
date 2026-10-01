<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            return response()->json(['message' => 'This action requires administrator privileges.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
