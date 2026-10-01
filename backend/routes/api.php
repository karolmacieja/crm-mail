<?php

use App\Http\Controllers\Api\Admin\LicenseController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gmail CRM API  (prefix: /api)
|--------------------------------------------------------------------------
| Auth: Sanctum bearer tokens (Authorization: Bearer <token>).
| CRM endpoints additionally require a valid license (HTTP 402 otherwise).
*/

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    // Available without a license so the extension can show account/renewal state.
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::middleware(['license', 'throttle:api'])->group(function () {
        Route::get('/contacts/lookup', [ContactController::class, 'lookup'])->name('contacts.lookup');
        Route::apiResource('contacts', ContactController::class)->whereNumber('contact');

        Route::apiResource('tasks', TaskController::class)->whereNumber('task');

        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [LicenseController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/license', [LicenseController::class, 'grant'])->name('users.license.grant');
        Route::delete('/users/{user}/license', [LicenseController::class, 'revoke'])->name('users.license.revoke');
    });
});
