<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activities created before the morph map stored full class names.
 */
return new class extends Migration
{
    private const MAP = [
        'App\\Models\\Contact' => 'contact',
        'App\\Models\\Reservation' => 'reservation',
        'App\\Models\\Task' => 'task',
        'App\\Models\\Reminder' => 'reminder',
    ];

    public function up(): void
    {
        foreach (self::MAP as $class => $alias) {
            DB::table('activities')->where('subject_type', $class)->update(['subject_type' => $alias]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $class => $alias) {
            DB::table('activities')->where('subject_type', $alias)->update(['subject_type' => $class]);
        }
    }
};
