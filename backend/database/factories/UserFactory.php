<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'role' => UserRole::Staff,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Holds a seat on an active license of their group. */
    public function licensed(int $days = 30): static
    {
        return $this->afterCreating(function (User $user) use ($days) {
            License::factory()->for($user->group)->create(['expires_at' => now()->addDays($days)])->assignTo($user);
        });
    }

    /** Holds a seat on an expired license. */
    public function expiredLicense(): static
    {
        return $this->afterCreating(function (User $user) {
            License::factory()->for($user->group)->expired()->create()->assignTo($user);
        });
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => UserRole::Manager]);
    }

    /** Platform owner: no group, sees every tenant. */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::MasterAdmin, 'group_id' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
