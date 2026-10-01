<?php

namespace Database\Factories;

use App\Models\ContactCategory;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactCategory>
 */
class ContactCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'group_id' => Group::factory(),
            'name' => ucfirst($name),
            'slug' => $name,
            'color' => fake()->randomElement(['blue', 'purple', 'green', 'orange']),
            'icon' => null,
            'sort_order' => 10,
        ];
    }
}
