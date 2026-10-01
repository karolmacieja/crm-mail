<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'group_id' => fn (array $attributes) => Contact::withoutGlobalScopes()->find($attributes['contact_id'])->group_id,
            'reservation_date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'reservation_time' => fake()->randomElement(['12:00', '13:30', '18:00', '19:00', '20:30']),
            'guests_count' => fake()->numberBetween(2, 12),
            'status' => 'confirmed',
            'table_label' => 'Stolik '.fake()->numberBetween(1, 20),
        ];
    }
}
