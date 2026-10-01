<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'company' => null,
            'phone' => fake()->numerify('+48 ### ### ###'),
            'status' => fake()->randomElement(ContactStatus::values()),
            'is_client' => false,
            'notes' => null,
        ];
    }

    public function client(): static
    {
        return $this->state(fn () => ['is_client' => true, 'status' => ContactStatus::Customer]);
    }
}
