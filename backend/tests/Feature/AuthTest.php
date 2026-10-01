<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_sanctum_token(): void
    {
        $user = User::factory()->licensed()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'Jane@Example.com',
            'password' => 'password',
            'device_name' => 'chrome',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'email', 'license' => ['status', 'is_valid', 'expires_at', 'key']]])
            ->assertJsonPath('user.license.status', 'active')
            ->assertJsonPath('user.license.is_valid', true);

        $this->assertNotSame($user->license_key, $response->json('user.license.key'), 'License key must be masked for non-admins.');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'chrome']);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_me_and_logout(): void
    {
        $user = User::factory()->expiredLicense()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.license.status', 'expired');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_requests_get_json_401(): void
    {
        $this->get('/api/contacts')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }
}
