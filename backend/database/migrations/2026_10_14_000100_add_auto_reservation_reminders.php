<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders created automatically for a reservation (preference
 * "Przypomnienie o rezerwacji"); they follow the booking's date and status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->boolean('auto_reservation')->default(false)->after('is_done');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', fn (Blueprint $table) => $table->dropColumn('auto_reservation'));
    }
};
