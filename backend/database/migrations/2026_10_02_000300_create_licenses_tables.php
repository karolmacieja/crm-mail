<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licenses are bought per group and have a number of seats;
 * license_user assigns those seats to individual users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('plan', 30)->default('standard');
            $table->unsignedSmallInteger('seats')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at');
            // active | suspended | cancelled  (App\Enums\LicenseStatus)
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status', 'expires_at']);
        });

        Schema::create('license_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            // A user holds at most one seat.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_user');
        Schema::dropIfExists('licenses');
    }
};
