<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);          // "B2B", "VIP", "Indywidualni"
            $table->string('slug', 60);
            $table->string('color', 20)->default('blue');   // Tailwind palette name used by the UI
            $table->string('icon', 40)->nullable();          // e.g. "fa-briefcase"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
        });

        // Default categories for groups that already exist (new groups get them via Group::created).
        $now = now();
        DB::table('groups')->orderBy('id')->each(function (object $group) use ($now) {
            DB::table('contact_categories')->insert([
                ['group_id' => $group->id, 'name' => 'B2B', 'slug' => 'b2b', 'color' => 'blue', 'icon' => 'fa-briefcase', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['group_id' => $group->id, 'name' => 'VIP', 'slug' => 'vip', 'color' => 'purple', 'icon' => 'fa-star', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['group_id' => $group->id, 'name' => 'Indywidualni', 'slug' => 'indywidualni', 'color' => 'green', 'icon' => 'fa-user', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_categories');
    }
};
