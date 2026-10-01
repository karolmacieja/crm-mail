<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders are separate from tasks: a point in time to be notified about
 * an email or a reservation. The time status (overdue / today / 7 days) is
 * derived from remind_at in the user's timezone (App\Models\Concerns\HasDueWindows),
 * so it is never stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // created by
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            // email | reservation | general  (App\Enums\ReminderType)
            $table->string('type', 20)->default('general');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('remind_at');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->string('source_email_id')->nullable();
            $table->string('source_email_subject')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'is_done', 'remind_at']);
            $table->index(['group_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
