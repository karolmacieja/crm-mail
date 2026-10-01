<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'group_id' => fn (array $attributes) => Contact::withoutGlobalScopes()->find($attributes['contact_id'])->group_id,
            'type' => 'general',
            'title' => fake()->sentence(4),
            'remind_at' => now()->addDays(2),
            'is_done' => false,
        ];
    }
}
