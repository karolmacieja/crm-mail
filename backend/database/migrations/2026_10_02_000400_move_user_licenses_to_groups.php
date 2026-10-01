<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data migration from the single-user model:
 * every existing non-admin user becomes the manager of their own group,
 * and their personal license becomes a 1-seat group license assigned to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')->where('role', '!=', 'master_admin')->whereNull('group_id')->orderBy('id')
            ->each(function (object $user) use ($now) {
                $groupId = DB::table('groups')->insertGetId([
                    'name' => $user->name,
                    'slug' => Str::slug($user->name).'-'.$user->id,
                    'timezone' => 'Europe/Warsaw',
                    'contact_email' => $user->email,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')->where('id', $user->id)->update(['group_id' => $groupId, 'role' => 'manager']);

                if ($user->license_key !== null) {
                    $licenseId = DB::table('licenses')->insertGetId([
                        'group_id' => $groupId,
                        'key' => $user->license_key,
                        'plan' => 'standard',
                        'seats' => 1,
                        'starts_at' => $user->created_at,
                        'expires_at' => $user->license_expires_at ?? $now,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('license_user')->insert([
                        'license_id' => $licenseId,
                        'user_id' => $user->id,
                        'assigned_at' => $now,
                    ]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['license_key']);
            $table->dropColumn(['license_key', 'license_expires_at', 'is_admin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('license_key', 64)->nullable()->unique();
            $table->timestamp('license_expires_at')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        DB::table('license_user')->join('licenses', 'licenses.id', '=', 'license_user.license_id')
            ->select('license_user.user_id', 'licenses.key', 'licenses.expires_at')
            ->orderBy('license_user.user_id')
            ->each(fn (object $row) => DB::table('users')->where('id', $row->user_id)->update([
                'license_key' => $row->key,
                'license_expires_at' => $row->expires_at,
            ]));

        DB::table('users')->where('role', 'master_admin')->update(['is_admin' => true]);
    }
};
