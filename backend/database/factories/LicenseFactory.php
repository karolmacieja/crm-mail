<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'plan' => 'standard',
            'seats' => 5,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn () => ['seats' => $seats]);
    }
}
