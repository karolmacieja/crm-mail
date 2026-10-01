<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // created by
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->unsignedSmallInteger('guests_count');
            // pending | confirmed | completed | cancelled | no_show  (App\Enums\ReservationStatus)
            $table->string('status', 20)->default('pending');
            $table->string('table_label', 50)->nullable();   // "Stolik 5", "Sala bankietowa"
            $table->string('occasion', 100)->nullable();     // "Wigilia firmowa", "Urodziny"
            $table->text('notes')->nullable();
            $table->string('source_email_id')->nullable();   // Gmail message id the booking came from
            $table->timestamps();

            $table->index(['group_id', 'reservation_date', 'reservation_time']);
            $table->index(['group_id', 'status']);
            $table->index('source_email_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
