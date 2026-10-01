<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('status', 20)->default('lead');
            $table->text('notes')->nullable();
            $table->timestamps();

            // A CRM owner can only have one contact per email address.
            $table->unique(['user_id', 'email']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
