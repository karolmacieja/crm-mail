<?php

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

/*
| Create a user (if needed) and grant/extend their license seat.
|   php artisan crm:license user@example.com --days=365 --name="Jane" --group="Restauracja Pod Lipą"
|   php artisan crm:license owner@example.com --admin        (Master Admin: no group, no license needed)
| The password is prompted for (never passed on the command line) when the user is new.
*/
Artisan::command('crm:license {email} {--days=30} {--name=} {--group=} {--admin} {--regenerate}', function (string $email) {
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
        $user->role = UserRole::MasterAdmin;
        $user->group_id = null;
        $user->save();
        $user->revokeLicense();
        $this->info("{$email} is now a Master Admin (no license required).");

        return 0;
    }

    if ($user->group_id === null) {
        $group = Group::create(['name' => $this->option('group') ?: $user->name]);
        $user->group_id = $group->id;
        $user->role = UserRole::Manager;
        $user->save();
        $this->info("Created group \"{$group->name}\".");
    }

    $license = $user->grantLicense($days, (bool) $this->option('regenerate'));

    $this->table(['Email', 'Group', 'License key', 'Seats', 'Expires at'], [[
        $user->email,
        $user->group->name,
        $license->key,
        $license->seatsUsed().'/'.$license->seats,
        $license->expires_at->toDateTimeString(),
    ]]);

    return 0;
})->purpose('Create a CRM user and grant or extend their license seat');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
