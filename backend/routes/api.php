<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\Admin\GroupController;
use App\Http\Controllers\Api\Admin\LicenseController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Auth\SessionAuthController;
use App\Http\Controllers\Api\Auth\TokenAuthController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CalendarFeedController;
use App\Http\Controllers\Api\ContactAccessRequestController;
use App\Http\Controllers\Api\ContactCategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactCustomFieldController;
use App\Http\Controllers\Api\ContactShareController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\Settings\DictionaryController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GastroFlowx API  (prefix: /api) — one backend, two clients
|--------------------------------------------------------------------------
|
| A) Gmail extension (restaurant staff)
|    Auth: Sanctum personal access token (Authorization: Bearer <token>) with
|    the "crm" ability. Middleware chain: client:extension → tenant → license.
|    No access to user/license management.
|
| B) Web panel (Master Admin, e.g. app.domena.pl)
|    Auth: Sanctum SPA session cookie (GET /sanctum/csrf-cookie, then
|    POST /api/web/login). Middleware chain: client:web → admin.
*/

// ---------------------------------------------------------------- A) Extension
Route::post('/auth/login', [TokenAuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware(['auth:sanctum', 'client:extension'])->group(function () {
    // Available without a license so the extension can show account/renewal state.
    Route::get('/auth/me', [TokenAuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [TokenAuthController::class, 'logout'])->name('auth.logout');

    // Personal settings: also without a valid license (e.g. to switch the calendar feed off).
    Route::middleware(['tenant', 'throttle:api'])->prefix('me')->group(function () {
        Route::get('/preferences', [PreferencesController::class, 'show'])->name('preferences.show');
        Route::patch('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
        Route::post('/calendar-feed', [CalendarFeedController::class, 'rotate'])->name('calendar-feed.rotate');
        Route::delete('/calendar-feed', [CalendarFeedController::class, 'disable'])->name('calendar-feed.disable');
    });

    Route::middleware(['tenant', 'license', 'throttle:api'])->group(function () {
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::get('/categories', [ContactCategoryController::class, 'index'])->name('categories.index');

        Route::get('/contacts/lookup', [ContactController::class, 'lookup'])->name('contacts.lookup');
        Route::post('/contacts/lookup-many', [ContactController::class, 'lookupMany'])->name('contacts.lookup-many');
        Route::apiResource('contacts', ContactController::class)->whereNumber('contact');

        Route::prefix('contacts/{contact}')->whereNumber('contact')->name('contacts.')->group(function () {
            Route::post('/custom-fields', [ContactCustomFieldController::class, 'store'])->name('custom-fields.store');
            Route::patch('/custom-fields/{field}', [ContactCustomFieldController::class, 'update'])->whereNumber('field')->name('custom-fields.update');
            Route::delete('/custom-fields/{field}', [ContactCustomFieldController::class, 'destroy'])->whereNumber('field')->name('custom-fields.destroy');

            Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
            Route::post('/notes', [ActivityController::class, 'storeNote'])->name('notes.store');
            Route::post('/emails', [ActivityController::class, 'storeEmail'])->name('emails.store');
            Route::post('/emails/import', [ActivityController::class, 'importEmails'])->name('emails.import');
            Route::patch('/activities/{activity}', [ActivityController::class, 'update'])->whereNumber('activity')->name('activities.update');
            Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->whereNumber('activity')->name('activities.destroy');

            // Sharing (owner only) and "Poproś o dostęp".
            Route::get('/shares', [ContactShareController::class, 'index'])->name('shares.index');
            Route::post('/shares', [ContactShareController::class, 'store'])->name('shares.store');
            Route::delete('/shares/{share}', [ContactShareController::class, 'destroy'])->whereNumber('share')->name('shares.destroy');
            Route::post('/transfer', [ContactShareController::class, 'transfer'])->name('transfer');
            Route::post('/access-requests', [ContactAccessRequestController::class, 'store'])->name('access-requests.store');
        });

        Route::get('/access-requests', [ContactAccessRequestController::class, 'index'])->name('access-requests.index');
        Route::post('/access-requests/{accessRequest}/approve', [ContactAccessRequestController::class, 'approve'])->whereNumber('accessRequest')->name('access-requests.approve');
        Route::post('/access-requests/{accessRequest}/decline', [ContactAccessRequestController::class, 'decline'])->whereNumber('accessRequest')->name('access-requests.decline');

        Route::apiResource('tasks', TaskController::class)->whereNumber('task');
        Route::apiResource('reminders', ReminderController::class)->whereNumber('reminder');
        Route::apiResource('reservations', ReservationController::class)->whereNumber('reservation');

        // Settings panel: the restaurant's dictionaries + personal preferences.
        Route::get('/settings', [DictionaryController::class, 'index'])->name('settings.index');
        Route::prefix('settings/{dictionary}')->where(['dictionary' => 'categories|statuses|task-categories|field-templates'])
            ->name('settings.')->group(function () {
                Route::post('/', [DictionaryController::class, 'store'])->name('store');
                Route::post('/reorder', [DictionaryController::class, 'reorder'])->name('reorder');
                Route::patch('/{id}', [DictionaryController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('/{id}', [DictionaryController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });

        Route::put('/calendar-events/{type}/{id}', [CalendarEventController::class, 'upsert'])
            ->where(['type' => 'task|reminder'])->whereNumber('id')->name('calendar-events.upsert');
        Route::delete('/calendar-events/{type}/{id}', [CalendarEventController::class, 'destroy'])
            ->where(['type' => 'task|reminder'])->whereNumber('id')->name('calendar-events.destroy');
    });
});

// Private iCalendar feed (subscribed from Google Calendar / Outlook / Apple Calendar).
Route::get('/calendar/{token}.ics', [CalendarFeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:60,1')
    ->name('calendar.feed');

// ---------------------------------------------------------------- B) Web panel
Route::post('/web/login', [SessionAuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('web.login');

Route::middleware(['auth:sanctum', 'client:web'])->group(function () {
    Route::get('/web/me', [SessionAuthController::class, 'me'])->name('web.me');
    Route::post('/web/logout', [SessionAuthController::class, 'logout'])->name('web.logout');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/stats', StatsController::class)->name('stats');

        Route::apiResource('groups', GroupController::class)->whereNumber('group');

        Route::apiResource('users', UserController::class)->whereNumber('user');
        Route::post('/users/{user}/revoke-tokens', [UserController::class, 'revokeTokens'])->whereNumber('user')->name('users.revoke-tokens');

        Route::apiResource('licenses', LicenseController::class)->whereNumber('license');
        Route::post('/licenses/{license}/extend', [LicenseController::class, 'extend'])->whereNumber('license')->name('licenses.extend');
        Route::post('/licenses/{license}/assignments', [LicenseController::class, 'assign'])->whereNumber('license')->name('licenses.assign');
        Route::delete('/licenses/{license}/assignments/{user}', [LicenseController::class, 'unassign'])
            ->whereNumber(['license', 'user'])->name('licenses.unassign');
    });
});
