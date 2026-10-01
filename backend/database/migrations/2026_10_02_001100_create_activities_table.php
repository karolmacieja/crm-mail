<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline ("Oś czasu"): emails, manual notes and system actions.
 *
 * subject_type/subject_id (polymorphic) = what the entry is about
 * (Contact, Reservation, Task, Reminder). contact_id is denormalised so a
 * contact's full timeline (including its reservations' events) is one indexed query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // author (null = system)
            // email | note | system  (App\Enums\ActivityType)
            $table->string('type', 20);
            $table->string('event', 60)->nullable();   // system events: "reservation.created", "task.completed"
            $table->string('title')->nullable();       // e.g. email subject
            $table->text('body')->nullable();          // note text / email snippet
            $table->json('meta')->nullable();          // email: message_id, thread_id, from, direction ...
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contact_id', 'occurred_at']);
            $table->index(['group_id', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
