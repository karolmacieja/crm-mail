<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('+48 ### ### ###'),
            'status' => fake()->randomElement(ContactStatus::values()),
            'notes' => null,
        ];
    }
}
