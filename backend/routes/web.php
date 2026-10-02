<?php

use Illuminate\Support\Facades\Route;

// The API has no pages of its own; send people who open the bare domain to the web panel
// (and don't advertise framework versions with Laravel's welcome page).
Route::redirect('/', config('app.web_panel_url'));
