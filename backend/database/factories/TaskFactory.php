<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'user_id' => fn (array $attributes) => Contact::find($attributes['contact_id'])->user_id,
            'title' => fake()->sentence(4),
            'due_date' => fake()->dateTimeBetween('-3 days', '+10 days'),
            'is_completed' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['is_completed' => true]);
    }
}
