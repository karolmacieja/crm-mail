<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactCustomField>
 */
class ContactCustomFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'group_id' => fn (array $attributes) => Contact::withoutGlobalScopes()->find($attributes['contact_id'])->group_id,
            'label' => 'Alergie',
            'key' => 'alergie',
            'type' => 'text',
            'value' => 'orzechy',
        ];
    }
}
