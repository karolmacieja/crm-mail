<?php

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\User;
use App\Support\Export\DataExporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

/*
| Data export (GDPR request, end of contract). Files land in storage/app/exports/ (not public).
|   php artisan crm:export --group=pod-lipa                          whole restaurant: export.json + kontakty.csv
|   php artisan crm:export --group=pod-lipa --email=gosc@firma.pl    everything about one client's e-mail
|   php artisan crm:export --user=kelner@pod-lipa.pl                 one employee's account data
| --group accepts the restaurant's id, slug or exact name.
*/
Artisan::command('crm:export {--group=} {--email=} {--user=}', function (DataExporter $exporter) {
    $write = function (string $label, array $data, ?array $rows = null) {
        $dir = storage_path('app/exports/'.now()->format('Y-m-d_His').'-'.Str::slug($label));
        File::ensureDirectoryExists($dir, 0700);
        File::put("{$dir}/export.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($rows !== null) {
            $csv = fopen("{$dir}/kontakty.csv", 'w');
            fwrite($csv, "\xEF\xBB\xBF"); // UTF-8 BOM: Polish characters in Excel
            foreach ($rows as $row) {
                fputcsv($csv, $row, ';');
            }
            fclose($csv);
        }

        $this->info("Zapisano: {$dir}");
        foreach (File::files($dir) as $file) {
            $this->line('  '.$file->getFilename().' ('.number_format($file->getSize() / 1024, 1).' KB)');
        }
    };

    if ($email = $this->option('user')) {
        $user = User::where('email', mb_strtolower(trim($email)))->first();
        if ($user === null) {
            $this->error("Nie ma użytkownika {$email}.");

            return 1;
        }
        $write('user-'.$user->email, $exporter->user($user));

        return 0;
    }

    $needle = (string) $this->option('group');
    $group = $needle === '' ? null : Group::query()
        ->where(fn ($q) => $q->where('slug', $needle)->orWhere('name', $needle)->orWhere('id', ctype_digit($needle) ? (int) $needle : 0))
        ->first();
    if ($group === null) {
        $this->error($needle === '' ? 'Podaj --group=<id|slug|nazwa> albo --user=<e-mail>.' : "Nie ma restauracji „{$needle}”.");
        $this->table(['ID', 'Slug', 'Nazwa'], Group::query()->orderBy('id')->get(['id', 'slug', 'name'])->toArray());

        return 1;
    }

    if ($contactEmail = $this->option('email')) {
        $data = $exporter->contactEmail($group, $contactEmail);
        if ($data['contacts'] === []) {
            $this->warn("W restauracji „{$group->name}” nie ma kontaktu {$contactEmail} – zapisuję pusty eksport jako potwierdzenie.");
        }
        $write($group->slug.'-'.$contactEmail, $data);

        return 0;
    }

    $write($group->slug, $exporter->restaurant($group), $exporter->contactRows($group));

    return 0;
})->purpose('Export a restaurant, one client e-mail or one user account to JSON (and CSV)');

Schedule::command('sanctum:prune-expired --hours=24')->daily();
