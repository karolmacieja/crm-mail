<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-restaurant dictionaries edited in the CRM settings panel:
 * contact statuses, task categories (board columns) and custom field templates.
 * contacts.status and tasks.type keep storing the dictionary `key`.
 */
return new class extends Migration
{
    public const STATUSES = [
        ['key' => 'lead', 'name' => 'Lead', 'color' => 'sky', 'sort_order' => 1, 'is_default' => true],
        ['key' => 'prospect', 'name' => 'Potencjalny klient', 'color' => 'amber', 'sort_order' => 2, 'is_default' => false],
        ['key' => 'customer', 'name' => 'Klient', 'color' => 'emerald', 'sort_order' => 3, 'is_default' => false],
        ['key' => 'inactive', 'name' => 'Nieaktywny', 'color' => 'gray', 'sort_order' => 4, 'is_default' => false],
    ];

    public const TASK_CATEGORIES = [
        ['key' => 'follow_up', 'name' => 'Kontakt i Follow-up', 'color' => 'blue', 'icon' => 'phone-volume', 'sort_order' => 1],
        ['key' => 'offer', 'name' => 'Oferty', 'color' => 'orange', 'icon' => 'file-invoice', 'sort_order' => 2],
        ['key' => 'internal', 'name' => 'Wewnętrzne (Restauracja)', 'color' => 'gray', 'icon' => 'utensils', 'sort_order' => 3],
    ];

    public function up(): void
    {
        Schema::create('contact_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 60);
            $table->string('color', 20)->default('gray');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false); // status of new contacts
            $table->timestamps();

            $table->unique(['group_id', 'key']);
        });

        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 60);
            $table->string('color', 20)->default('gray');
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'key']);
        });

        // Suggestions shown under "Dodaj pole" (e.g. Alergie, NIP).
        Schema::create('custom_field_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('type', 20)->default('text');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'label']);
        });

        $now = now();
        DB::table('groups')->orderBy('id')->each(function (object $group) use ($now) {
            $stamp = ['group_id' => $group->id, 'created_at' => $now, 'updated_at' => $now];
            DB::table('contact_statuses')->insert(array_map(fn ($row) => $row + $stamp, self::STATUSES));
            DB::table('task_categories')->insert(array_map(fn ($row) => $row + $stamp, self::TASK_CATEGORIES));
            DB::table('custom_field_templates')->insert([
                ['label' => 'Alergie', 'type' => 'text', 'sort_order' => 1] + $stamp,
                ['label' => 'NIP', 'type' => 'text', 'sort_order' => 2] + $stamp,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_templates');
        Schema::dropIfExists('task_categories');
        Schema::dropIfExists('contact_statuses');
    }
};
