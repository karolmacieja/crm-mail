<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pick the response language (validation errors, messages) from the
 * Accept-Language header sent by the extension. Unsupported languages
 * fall back to the application default.
 */
class SetLocaleFromHeader
{
    public const SUPPORTED = ['en', 'pl'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        if ($locale !== null && $request->headers->has('Accept-Language')) {
            app()->setLocale($locale);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
