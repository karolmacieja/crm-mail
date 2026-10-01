<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => 'contact',
            'subject_id' => Contact::factory(),
            'contact_id' => fn (array $attributes) => $attributes['subject_id'],
            'group_id' => fn (array $attributes) => Contact::withoutGlobalScopes()->find($attributes['subject_id'])->group_id,
            'type' => 'note',
            'body' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }
}
