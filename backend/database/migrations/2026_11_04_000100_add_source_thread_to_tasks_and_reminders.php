<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gmail thread of the email a task / reminder was created from, so the
 * extension can open that conversation (and reply) on the reminder's day.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'reminders'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('source_thread_id')->nullable()->after('source_email_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'reminders'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('source_thread_id'));
        }
    }
};
