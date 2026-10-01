<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

/*
| Create a user (if needed) and grant/extend their license.
|   php artisan crm:license user@example.com --days=365 --name="Jane" --admin
| The password is prompted for (never passed on the command line) when the user is new.
*/
Artisan::command('crm:license {email} {--days=30} {--name=} {--admin} {--regenerate}', function (string $email) {
    $days = (int) $this->option('days');

    $validator = Validator::make(['email' => $email, 'days' => $days], [
        'email' => ['required', 'email'],
        'days' => ['integer', 'min:1', 'max:3650'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $email = mb_strtolower($email);
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $password = $this->secret('Password for the new user (min. 8 characters)');

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return 1;
        }

        $user = User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Created user {$email}.");
    }

    if ($this->option('admin')) {
        $user->is_admin = true;
        $user->save();
    }

    $user->grantLicense($days, (bool) $this->option('regenerate'));

    $this->table(['Email', 'License key', 'Expires at', 'Admin'], [[
        $user->email,
        $user->license_key,
        $user->license_expires_at->toDateTimeString(),
        $user->is_admin ? 'yes' : 'no',
    ]]);

    return 0;
})->purpose('Create a CRM user and grant or extend their license');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
