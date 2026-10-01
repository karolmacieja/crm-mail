<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: master admins operate across all groups.
            $table->foreignId('group_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // master_admin | manager | staff  (App\Enums\UserRole)
            $table->string('role', 20)->default('staff')->after('password');
            $table->index(['group_id', 'role']);
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'master_admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'master_admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'role']);
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('role');
        });
    }
};
