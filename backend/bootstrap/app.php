<?php

use App\Http\Middleware\EnsureClientType;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureValidLicense;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cookie/session auth for the web panel (SANCTUM_STATEFUL_DOMAINS);
        // token requests from the Gmail extension are unaffected.
        $middleware->statefulApi();

        $middleware->alias([
            'client' => EnsureClientType::class,
            'tenant' => EnsureTenantMember::class,
            'license' => EnsureValidLicense::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->appendToGroup('api', SetLocaleFromHeader::class);

        // There is no web login page: API guests get a JSON 401, never a redirect.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The API is consumed exclusively by the extension: always answer
        // with JSON (401/404/422/500 ...) instead of HTML or login redirects.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
