<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Support\Tenancy\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job (scoped = reset between Octane requests and queue jobs).
        $this->app->scoped(Tenancy::class);
    }

    public function boot(): void
    {
        // Short, stable names in activities.subject_type (and in the API) instead of PHP class names.
        Relation::morphMap([
            'contact' => Contact::class,
            'reservation' => Reservation::class,
            'task' => Task::class,
            'reminder' => Reminder::class,
        ]);

        // Brute-force protection: 5 attempts per minute per email+IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
