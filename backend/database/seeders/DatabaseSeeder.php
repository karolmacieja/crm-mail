<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping demo data in production. Use `php artisan crm:license` to create real accounts.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
