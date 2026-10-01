<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAsWebPanel($this->admin);
    }

    public function test_group_crud_with_default_categories(): void
    {
        $id = $this->postJson('/api/admin/groups', ['name' => 'Pierogarnia Babci', 'contact_email' => 'kontakt@pierogi.pl'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'pierogarnia-babci')
            ->assertJsonPath('data.timezone', 'Europe/Warsaw')
            ->json('data.id');

        $this->assertSame(3, Group::find($id)->contactCategories()->count());

        $this->patchJson("/api/admin/groups/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/admin/groups?is_active=0')->assertJsonCount(1, 'data');
    }

    public function test_deleting_a_group_requires_confirmation_and_removes_its_data(): void
    {
        $group = Group::factory()->create();
        $staff = User::factory()->for($group)->licensed()->create();
        $staff->createToken('chrome', ['crm']);
        Contact::factory()->for($group)->create();

        $this->deleteJson("/api/admin/groups/{$group->id}")->assertStatus(422)->assertJsonValidationErrors('confirm');

        $this->deleteJson("/api/admin/groups/{$group->id}?confirm={$group->slug}")->assertNoContent();

        $this->assertModelMissing($group);
        $this->assertModelMissing($staff);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_create_staff_with_a_seat(): void
    {
        $group = Group::factory()->create();
        $license = License::factory()->for($group)->seats(1)->create();

        $this->postJson('/api/admin/users', [
            'name' => 'Piotr Kelner', 'email' => 'Piotr@Roma.pl', 'password' => 'tajne-haslo-1',
            'role' => 'staff', 'group_id' => $group->id, 'license_id' => $license->id,
        ])->assertCreated()
            ->assertJsonPath('data.email', 'piotr@roma.pl')
            ->assertJsonPath('data.group.id', $group->id)
            ->assertJsonPath('data.license.is_valid', true);

        // No seat left.
        $this->postJson('/api/admin/users', [
            'name' => 'Druga Osoba', 'email' => 'druga@roma.pl', 'password' => 'tajne-haslo-1',
            'role' => 'staff', 'group_id' => $group->id, 'license_id' => $license->id,
        ])->assertStatus(422)->assertJsonValidationErrors('license_id');
        $this->assertDatabaseMissing('users', ['email' => 'druga@roma.pl']); // rolled back
    }

    public function test_user_validation_rules_for_roles_and_groups(): void
    {
        $group = Group::factory()->create();
        $payload = ['name' => 'X', 'email' => 'x@x.pl', 'password' => 'tajne-haslo-1'];

        $this->postJson('/api/admin/users', $payload + ['role' => 'staff'])->assertJsonValidationErrors('group_id');
        $this->postJson('/api/admin/users', $payload + ['role' => 'master_admin', 'group_id' => $group->id])->assertJsonValidationErrors('group_id');
        $this->postJson('/api/admin/users', $payload + ['role' => 'master_admin'])->assertCreated()->assertJsonPath('data.group', null);
    }

    public function test_moving_user_to_another_group_drops_seat_and_tokens(): void
    {
        $user = User::factory()->licensed()->create();
        $user->createToken('chrome', ['crm']);
        $other = Group::factory()->create();

        $this->patchJson("/api/admin/users/{$user->id}", ['group_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.group.id', $other->id)
            ->assertJsonPath('data.license.status', 'missing');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_cannot_delete_yourself(): void
    {
        $this->deleteJson("/api/admin/users/{$this->admin->id}")->assertStatus(422);
        $this->deleteJson('/api/admin/users/'.User::factory()->create()->id)->assertNoContent();
    }

    public function test_license_lifecycle_and_seats(): void
    {
        $group = Group::factory()->create();
        [$a, $b] = User::factory()->for($group)->count(2)->create();

        $id = $this->postJson('/api/admin/licenses', ['group_id' => $group->id, 'seats' => 1, 'days' => 30, 'plan' => 'pro'])
            ->assertCreated()
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.seats_used', 0)
            ->json('data.id');

        $this->postJson("/api/admin/licenses/{$id}/assignments", ['user_id' => $a->id])->assertOk()->assertJsonPath('data.seats_used', 1);
        $this->postJson("/api/admin/licenses/{$id}/assignments", ['user_id' => $b->id])->assertStatus(422);
        $this->postJson("/api/admin/licenses/{$id}/assignments", ['user_id' => User::factory()->create()->id])
            ->assertStatus(422); // other group

        $this->patchJson("/api/admin/licenses/{$id}", ['seats' => 2])->assertOk();
        $this->postJson("/api/admin/licenses/{$id}/assignments", ['user_id' => $b->id])->assertOk()->assertJsonCount(2, 'data.users');
        $this->patchJson("/api/admin/licenses/{$id}", ['seats' => 1])->assertStatus(422)->assertJsonValidationErrors('seats');

        $before = License::find($id)->expires_at;
        $this->postJson("/api/admin/licenses/{$id}/extend", ['days' => 10])->assertOk();
        $this->assertTrue(License::find($id)->expires_at->equalTo($before->copy()->addDays(10)));

        $this->deleteJson("/api/admin/licenses/{$id}/assignments/{$a->id}")->assertOk()->assertJsonPath('data.seats_used', 1);
        $this->patchJson("/api/admin/licenses/{$id}", ['status' => 'suspended'])->assertJsonPath('data.state', 'suspended');
        $this->assertFalse($b->fresh()->hasValidLicense());

        // $a (unassigned) and the user from the other group have no seat.
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('data.users.without_seat', 2);
    }
}
