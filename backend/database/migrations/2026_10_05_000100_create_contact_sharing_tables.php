<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private contacts with sharing. contacts.user_id becomes the contact's
 * owner ("opiekun"): existing contacts stay with whoever created them and
 * are no longer visible to the rest of the team until shared. Contacts
 * whose creator was removed (user_id = null) remain shared with the team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // null = the whole restaurant team.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // What the share covers.
            $table->boolean('details')->default(false);       // phone, company, notes, custom fields
            $table->boolean('notes')->default(false);         // notes on the timeline
            $table->boolean('emails')->default(false);        // all logged emails
            $table->boolean('reservations')->default(false);
            $table->boolean('work')->default(false);          // tasks and reminders
            // Individual emails (activity ids) shared without the whole correspondence.
            $table->json('activity_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contact_id', 'user_id']);
        });

        Schema::create('contact_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who asks
            $table->string('status', 20)->default('pending');               // pending | approved | declined
            $table->string('message', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_access_requests');
        Schema::dropIfExists('contact_shares');
    }
};
