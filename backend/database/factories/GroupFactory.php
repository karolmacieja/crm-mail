<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Restauracja '.fake()->unique()->lastName(),
            'timezone' => 'Europe/Warsaw',
            'contact_email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}
