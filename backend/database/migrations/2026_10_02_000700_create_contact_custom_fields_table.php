<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flexible per-contact fields such as "Alergie", "Ulubiony stolik", "NIP".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);        // shown to the user: "Alergie"
            $table->string('key', 100);          // stable slug: "alergie"
            $table->string('type', 20)->default('text'); // App\Enums\CustomFieldType
            $table->text('value')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['contact_id', 'key']);
            $table->index(['group_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_custom_fields');
    }
};
