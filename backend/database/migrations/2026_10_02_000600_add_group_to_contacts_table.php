<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->after('group_id')
                ->constrained('contact_categories')->nullOnDelete();
            $table->string('company')->nullable()->after('name');
            // false = plain address book entry ("Kontakty"), true = client profile ("Klienci")
            $table->boolean('is_client')->default(false)->after('status');
            $table->timestamp('last_activity_at')->nullable()->after('notes');
        });

        // Backfill tenant from the contact's creator.
        DB::table('contacts')->orderBy('id')->each(function (object $contact) {
            DB::table('contacts')->where('id', $contact->id)->update([
                'group_id' => DB::table('users')->where('id', $contact->user_id)->value('group_id'),
                'is_client' => $contact->status === 'customer',
                'last_activity_at' => $contact->updated_at,
            ]);
        });
        DB::table('contacts')->whereNull('group_id')->delete(); // orphans of master admins (none expected)

        // Deleting a staff member must not delete the group's data. The foreign key
        // goes first: MySQL won't drop the indexes that back it.
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'email']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable(false)->change();
            // user_id now means "created by" and survives the user being removed.
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['group_id', 'email']);
            $table->index(['group_id', 'is_client', 'category_id']);
            $table->index(['group_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        // Foreign keys first: MySQL won't drop the indexes that back them.
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['group_id']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'email']);
            $table->dropIndex(['group_id', 'is_client', 'category_id']);
            $table->dropIndex(['group_id', 'last_activity_at']);
            $table->dropColumn(['category_id', 'group_id', 'company', 'is_client', 'last_activity_at']);
            $table->unique(['user_id', 'email']);
            $table->index(['user_id', 'status']);
        });
    }
};
