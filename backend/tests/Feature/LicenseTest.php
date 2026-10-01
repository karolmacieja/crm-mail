<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * License gate for the extension's CRM endpoints.
 */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_license_is_rejected_with_402(): void
    {
        $this->actingAsExtension(User::factory()->expiredLicense()->create());

        $this->getJson('/api/contacts')
            ->assertStatus(402)
            ->assertJsonPath('code', 'license_expired');
    }

    public function test_missing_seat_is_rejected_with_402(): void
    {
        $this->actingAsExtension(User::factory()->create());

        $this->getJson('/api/contacts')
            ->assertStatus(402)
            ->assertJsonPath('code', 'license_missing');
    }

    public function test_staff_with_a_seat_passes(): void
    {
        $this->actingAsExtension(User::factory()->licensed()->create());

        $this->getJson('/api/contacts')->assertOk();
    }

    public function test_me_works_without_license_to_show_renewal_state(): void
    {
        $this->actingAsExtension(User::factory()->expiredLicense()->create());

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.license.status', 'expired');
    }

    public function test_inactive_group_blocks_crm(): void
    {
        $user = User::factory()->licensed()->create();
        $user->group->update(['is_active' => false]);
        $this->actingAsExtension($user);

        $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'group_inactive');
    }

    public function test_license_command_creates_group_user_and_seat(): void
    {
        $this->artisan('crm:license', ['email' => 'New@Example.com', '--days' => 14, '--group' => 'Bistro Nowe'])
            ->expectsQuestion('Password for the new user (min. 8 characters)', 'secret-password')
            ->assertSuccessful();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasValidLicense());
        $this->assertSame('Bistro Nowe', $user->group->name);
        $this->assertSame(UserRole::Manager, $user->role);
        $this->assertMatchesRegularExpression('/^GFX-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $user->license->key);
        $this->assertSame(1, Group::count());
    }
}
