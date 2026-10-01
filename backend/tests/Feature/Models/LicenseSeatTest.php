<?php

namespace Tests\Feature\Models;

use App\Enums\LicenseStatus;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseSeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_seats_limit_assignments(): void
    {
        $group = Group::factory()->create();
        $license = License::factory()->for($group)->seats(2)->create();
        [$a, $b, $c] = User::factory()->for($group)->count(3)->create();

        $license->assignTo($a);
        $license->assignTo($b);
        $license->assignTo($b); // idempotent

        $this->assertSame(0, $license->seatsAvailable());
        $this->assertTrue($a->hasValidLicense());

        $this->expectException(DomainException::class);
        $license->assignTo($c);
    }

    public function test_cannot_assign_seat_to_user_of_another_group(): void
    {
        $license = License::factory()->create();

        $this->expectException(DomainException::class);
        $license->assignTo(User::factory()->create());
    }

    public function test_user_moves_to_new_license_and_frees_old_seat(): void
    {
        $group = Group::factory()->create();
        $old = License::factory()->for($group)->seats(1)->create();
        $new = License::factory()->for($group)->seats(1)->create();
        $user = User::factory()->for($group)->create();

        $old->assignTo($user);
        $new->assignTo($user);

        $this->assertSame($new->id, $user->fresh()->license->id);
        $this->assertSame(1, $old->seatsAvailable());
    }

    public function test_license_states(): void
    {
        $this->assertSame('active', License::factory()->create()->state());
        $this->assertSame('expired', License::factory()->expired()->create()->state());
        $this->assertSame('scheduled', License::factory()->create(['starts_at' => now()->addDay()])->state());
        $this->assertSame('suspended', License::factory()->create(['status' => LicenseStatus::Suspended])->state());
        $this->assertSame('missing', User::factory()->create()->licenseStatus());

        $this->assertSame(1, License::active()->count());
    }

    public function test_extend_adds_to_remaining_time_or_restarts_when_expired(): void
    {
        $active = License::factory()->create(['expires_at' => now()->addDays(10)]);
        $expired = License::factory()->create(['expires_at' => now()->subDays(10)]);

        $this->assertEqualsWithDelta(40, now()->diffInDays($active->extend(30)->expires_at), 0.01);
        $this->assertEqualsWithDelta(30, now()->diffInDays($expired->extend(30)->expires_at), 0.01);
    }
}
