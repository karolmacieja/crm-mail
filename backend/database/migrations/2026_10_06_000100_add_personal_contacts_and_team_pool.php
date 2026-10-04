<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business correspondence ("korespondencja firmowa"): contacts in a private
 * category get one card per person (personal_key = that person's id, 0 for
 * ordinary contacts), so the same e-mail may appear once per colleague.
 * Selected emails from such cards go to a shared team pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_categories', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('icon');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_key')->default(0)->after('user_id');
        });

        // New unique index first: MySQL needs an index starting with group_id for its foreign key.
        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['group_id', 'email', 'personal_key'], 'contacts_group_email_personal_unique');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'email']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->timestamp('team_shared_at')->nullable()->after('meta');
            $table->foreignId('team_shared_by')->nullable()->after('team_shared_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['team_shared_by']);
            $table->dropColumn(['team_shared_at', 'team_shared_by']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['group_id', 'email']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_group_email_personal_unique');
            $table->dropColumn('personal_key');
        });

        Schema::table('contact_categories', fn (Blueprint $table) => $table->dropColumn('is_private'));
    }
};
