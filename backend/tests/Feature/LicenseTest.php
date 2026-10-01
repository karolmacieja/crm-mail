<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_license_is_rejected_with_402(): void
    {
        Sanctum::actingAs(User::factory()->expiredLicense()->create());

        $this->getJson('/api/contacts')
            ->assertStatus(402)
            ->assertJsonPath('code', 'license_expired');
    }

    public function test_missing_license_is_rejected_with_402(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/contacts')
            ->assertStatus(402)
            ->assertJsonPath('code', 'license_missing');
    }

    public function test_valid_license_and_admins_pass(): void
    {
        Sanctum::actingAs(User::factory()->licensed()->create());
        $this->getJson('/api/contacts')->assertOk();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/contacts')->assertOk();
    }

    public function test_admin_can_grant_extend_and_revoke_licenses(): void
    {
        $customer = User::factory()->licensed(10)->create();
        $customer->createToken('chrome');
        $originalExpiry = $customer->license_expires_at;

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/users/{$customer->id}/license", ['days' => 30])
            ->assertOk()
            ->assertJsonPath('data.license.key', $customer->license_key);

        // Extending an active license stacks on top of the remaining time.
        $this->assertTrue($customer->refresh()->license_expires_at->equalTo($originalExpiry->copy()->addDays(30)));

        $this->deleteJson("/api/admin/users/{$customer->id}/license")
            ->assertOk()
            ->assertJsonPath('data.license.is_valid', false);

        $this->assertCount(0, $customer->tokens()->get());
    }

    public function test_non_admin_cannot_manage_licenses(): void
    {
        $user = User::factory()->licensed()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/admin/users/{$user->id}/license", ['days' => 365])->assertForbidden();
    }

    public function test_license_command_creates_user_and_license(): void
    {
        $this->artisan('crm:license', ['email' => 'New@Example.com', '--days' => 14])
            ->expectsQuestion('Password for the new user (min. 8 characters)', 'secret-password')
            ->assertSuccessful();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasValidLicense());
        $this->assertMatchesRegularExpression('/^CRM-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $user->license_key);
    }
}
