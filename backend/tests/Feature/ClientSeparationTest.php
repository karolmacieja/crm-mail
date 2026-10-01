<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two front-ends, one backend: the extension (tokens) must never reach the
 * admin API, and the web panel (session) is for the Master Admin only.
 */
class ClientSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const PANEL = ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];

    public function test_extension_login_issues_crm_token_for_staff(): void
    {
        User::factory()->licensed()->create(['email' => 'kelner@roma.pl']);

        $token = $this->postJson('/api/auth/login', ['email' => 'kelner@roma.pl', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonStructure(['user' => ['group' => ['id', 'name', 'timezone']]])
            ->json('token');

        $this->assertSame(['crm'], PersonalAccessToken::findToken($token)->abilities);
    }

    public function test_extension_login_refuses_master_admin_and_groupless_users(): void
    {
        User::factory()->admin()->create(['email' => 'admin@gfx.pl']);
        User::factory()->create(['email' => 'nogroup@gfx.pl', 'group_id' => null]);
        $inactive = User::factory()->create(['email' => 'closed@gfx.pl']);
        $inactive->group->update(['is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'admin@gfx.pl', 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('code', 'use_web_panel');
        $this->postJson('/api/auth/login', ['email' => 'nogroup@gfx.pl', 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('code', 'no_group');
        $this->postJson('/api/auth/login', ['email' => 'closed@gfx.pl', 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('code', 'group_inactive');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_web_panel_session_login_for_master_admin(): void
    {
        User::factory()->admin()->create(['email' => 'admin@gfx.pl']);

        $this->withHeaders(self::PANEL)->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');

        $this->withHeaders(self::PANEL)
            ->postJson('/api/web/login', ['email' => 'admin@gfx.pl', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'master_admin');

        $this->assertAuthenticated('web');
        $this->withHeaders(self::PANEL)->getJson('/api/web/me')->assertOk()->assertJsonPath('data.email', 'admin@gfx.pl');
        $this->withHeaders(self::PANEL)->getJson('/api/admin/stats')->assertOk();

        $this->withHeaders(self::PANEL)->postJson('/api/web/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_web_panel_refuses_staff(): void
    {
        User::factory()->licensed()->create(['email' => 'kelner@roma.pl']);

        $this->withHeaders(self::PANEL)
            ->postJson('/api/web/login', ['email' => 'kelner@roma.pl', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'web_panel_admins_only');
        $this->assertGuest('web');
    }

    public function test_tokens_never_reach_the_admin_api_even_for_master_admin(): void
    {
        $this->actingAsExtension(User::factory()->admin()->create());

        $this->getJson('/api/admin/licenses')->assertForbidden()->assertJsonPath('code', 'wrong_client');
        $this->postJson('/api/admin/groups', ['name' => 'X'])->assertForbidden();
    }

    public function test_master_admin_session_cannot_use_crm_endpoints(): void
    {
        $this->actingAsWebPanel(User::factory()->admin()->create());

        $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'wrong_client');
    }

    public function test_master_admin_token_cannot_use_crm_either(): void
    {
        $this->actingAsExtension(User::factory()->admin()->create());

        $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'no_group');
    }

    public function test_token_without_crm_ability_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->licensed()->create(), ['something-else']);

        $this->getJson('/api/contacts')->assertForbidden()->assertJsonPath('code', 'wrong_client');
    }

    public function test_staff_session_cannot_reach_admin_api(): void
    {
        $this->actingAsWebPanel(User::factory()->licensed()->create());

        $this->getJson('/api/admin/stats')->assertForbidden()->assertJsonPath('code', 'admin_only');
    }
}
