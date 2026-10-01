<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('title');
            // follow_up | offer | internal  (App\Enums\TaskType)
            $table->string('type', 20)->default('follow_up')->after('description');
            // normal | high  (App\Enums\TaskPriority)
            $table->string('priority', 10)->default('normal')->after('type');
            $table->string('source_email_id')->nullable()->after('priority');      // Gmail message id
            $table->string('source_email_subject')->nullable()->after('source_email_id');
        });

        DB::table('tasks')->orderBy('id')->each(function (object $task) {
            DB::table('tasks')->where('id', $task->id)->update([
                'group_id' => DB::table('contacts')->where('id', $task->contact_id)->value('group_id'),
            ]);
        });
        DB::table('tasks')->whereNull('group_id')->delete();

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_completed', 'due_date']);
            // Deleting a staff member must not delete the group's data.
            $table->dropForeign(['user_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable(false)->change();
            $table->foreignId('user_id')->nullable()->change(); // created by
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->change(); // internal tasks have no contact

            $table->index(['group_id', 'is_completed', 'due_date']);
            $table->index(['group_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'is_completed', 'due_date']);
            $table->dropIndex(['group_id', 'type']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn(['description', 'type', 'priority', 'source_email_id', 'source_email_subject']);
            $table->index(['user_id', 'is_completed', 'due_date']);
        });
    }
};
