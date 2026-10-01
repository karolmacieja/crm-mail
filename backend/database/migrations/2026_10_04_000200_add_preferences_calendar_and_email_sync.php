<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Personal settings (default reminder hour, calendar options, Google integration...).
            $table->json('preferences')->nullable()->after('role');
            // Private iCalendar feed: lookup by hash, encrypted copy to show the URL again.
            $table->string('calendar_token_hash', 64)->nullable()->unique()->after('preferences');
            $table->text('calendar_token')->nullable()->after('calendar_token_hash');
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Last time the Gmail correspondence history was imported (null = never).
            $table->timestamp('email_history_synced_at')->nullable()->after('last_activity_at');
        });

        // Events created in a person's own calendar (Google Calendar API) per task/reminder.
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('eventable');
            $table->string('provider', 20)->default('google');
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['user_id', 'eventable_type', 'eventable_id', 'provider'], 'calendar_events_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('email_history_synced_at'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['calendar_token_hash']);
            $table->dropColumn(['preferences', 'calendar_token_hash', 'calendar_token']);
        });
    }
};
