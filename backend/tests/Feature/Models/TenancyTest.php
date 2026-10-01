<?php

namespace Tests\Feature\Models;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\Group;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\MissingGroupContext;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private Group $pizzeria;

    private Group $sushi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pizzeria = Group::factory()->create(['name' => 'Pizzeria Roma']);
        $this->sushi = Group::factory()->create(['name' => 'Sushi Bar']);

        foreach ([$this->pizzeria, $this->sushi] as $group) {
            $contact = Contact::factory()->for($group)->create();
            Task::factory()->for($contact)->create();
            Reservation::factory()->for($contact)->create();
            Reminder::factory()->for($contact)->create();
            $contact->addNote("Notatka {$group->name}");
        }
    }

    public function test_each_model_only_returns_the_current_users_group(): void
    {
        Sanctum::actingAs(User::factory()->for($this->pizzeria)->create());

        foreach ([Contact::class, Task::class, Reservation::class, Reminder::class, ContactCategory::class] as $model) {
            $this->assertTrue(
                $model::query()->pluck('group_id')->every(fn ($id) => $id === $this->pizzeria->id),
                "{$model} leaked rows of another group",
            );
            $this->assertGreaterThan(0, $model::count(), "{$model} returned nothing for its own group");
        }

        // Activities: note + system "reservation.created" for the pizzeria contact only.
        $this->assertSame([$this->pizzeria->id], Activity::query()->distinct()->pluck('group_id')->all());
    }

    public function test_other_groups_records_are_not_found_by_id(): void
    {
        Sanctum::actingAs(User::factory()->for($this->pizzeria)->licensed()->create());
        $foreign = Contact::withoutGlobalScopes()->where('group_id', $this->sushi->id)->first();

        $this->assertNull(Contact::find($foreign->id));
        $this->getJson("/api/contacts/{$foreign->id}")->assertNotFound();
    }

    public function test_group_id_is_filled_from_the_signed_in_user(): void
    {
        $user = User::factory()->for($this->sushi)->create();
        Sanctum::actingAs($user);

        $contact = $user->contacts()->create(['email' => 'nowy@gosc.pl']);

        $this->assertSame($this->sushi->id, $contact->group_id);
        $this->assertSame($user->id, $contact->creator->id);
    }

    public function test_group_id_cannot_be_mass_assigned(): void
    {
        Sanctum::actingAs(User::factory()->for($this->sushi)->create());

        $contact = Contact::create(['email' => 'x@y.pl', 'group_id' => $this->pizzeria->id]);

        $this->assertSame($this->sushi->id, $contact->group_id);
    }

    public function test_creating_without_any_group_context_fails_loudly(): void
    {
        $this->expectException(MissingGroupContext::class);

        Contact::create(['email' => 'orphan@example.com']);
    }

    public function test_user_without_group_sees_nothing(): void
    {
        $user = User::factory()->create(['group_id' => null]);
        Sanctum::actingAs($user);

        $this->assertSame(0, Contact::count());
    }

    public function test_master_admin_sees_all_groups(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->assertSame(2, Contact::count());
        $this->assertSame(1, Contact::forGroup($this->sushi)->count());
    }

    public function test_run_as_and_without_scope(): void
    {
        $tenancy = app(Tenancy::class);

        $emails = $tenancy->runAs($this->sushi, fn () => Contact::pluck('group_id')->unique()->all());
        $this->assertSame([$this->sushi->id], $emails);

        Sanctum::actingAs(User::factory()->for($this->pizzeria)->create());
        $this->assertSame(1, Contact::count());
        $this->assertSame(2, $tenancy->withoutScope(fn () => Contact::count()));
        $this->assertSame(1, Contact::count(), 'scope must be restored after withoutScope()');
    }

    public function test_new_group_gets_default_categories(): void
    {
        $group = Group::factory()->create(['name' => 'Bistro Nowe']);

        $this->assertSame('bistro-nowe', $group->slug);
        $this->assertSame(['b2b', 'vip', 'indywidualni'], $group->contactCategories()->pluck('slug')->all());
    }
}
