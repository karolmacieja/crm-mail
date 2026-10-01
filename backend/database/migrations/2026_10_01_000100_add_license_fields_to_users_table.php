<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('license_key', 64)->nullable()->unique()->after('password');
            $table->timestamp('license_expires_at')->nullable()->after('license_key');
            $table->boolean('is_admin')->default(false)->after('license_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['license_key']);
            $table->dropColumn(['license_key', 'license_expires_at', 'is_admin']);
        });
    }
};
