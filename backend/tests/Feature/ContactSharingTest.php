<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSharingTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $owner;

    private User $colleague;

    private User $third;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create();
        $this->owner = User::factory()->for($this->group)->licensed()->create(['name' => 'Anna Opiekun']);
        $this->colleague = User::factory()->for($this->group)->licensed()->create(['name' => 'Bartek Kelner']);
        $this->third = User::factory()->for($this->group)->licensed()->create();

        $this->actingAsExtension($this->owner);
        $id = $this->postJson('/api/contacts', ['email' => 'gosc@firma.pl', 'name' => 'Gość', 'phone' => '+48 600 000 000', 'is_client' => true])
            ->assertCreated()
            ->assertJsonPath('data.owner.name', 'Anna Opiekun')
            ->assertJsonPath('data.access.is_owner', true)
            ->json('data.id');
        $this->contact = Contact::find($id);
    }

    private function as(User $user): static
    {
        return $this->actingAsExtension($user);
    }

    public function test_new_contacts_are_private_to_their_owner(): void
    {
        $this->getJson('/api/contacts')->assertJsonCount(1, 'data');

        $this->as($this->colleague);
        $this->getJson('/api/contacts')->assertJsonCount(0, 'data');
        $this->getJson("/api/contacts/{$this->contact->id}")->assertNotFound();
        $this->getJson("/api/contacts/{$this->contact->id}/activities")->assertNotFound();
        $this->postJson('/api/contacts/lookup-many', ['emails' => ['gosc@firma.pl']])->assertJsonCount(0, 'data');
        $this->getJson('/api/dashboard/summary')->assertJsonPath('data.contacts.total', 0);
        $this->postJson('/api/tasks', ['title' => 'Oddzwonić', 'contact_id' => $this->contact->id])->assertStatus(422);
    }

    public function test_lookup_names_the_owner_and_colleague_can_request_access(): void
    {
        $this->as($this->colleague);
        $this->getJson('/api/contacts/lookup?email=GOSC@firma.pl')
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.owned_by_colleague.owner.name', 'Anna Opiekun')
            ->assertJsonPath('meta.owned_by_colleague.access_requested', false);

        // One card per e-mail: creating a duplicate names the owner.
        $this->postJson('/api/contacts', ['email' => 'gosc@firma.pl'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('crm.sharing.email_owned_by', ['name' => 'Anna Opiekun']));

        $requestId = $this->postJson("/api/contacts/{$this->contact->id}/access-requests", ['message' => 'Obsługuję jego rezerwację'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/contacts/{$this->contact->id}/access-requests")->assertOk();
        $this->getJson('/api/contacts/lookup?email=gosc@firma.pl')->assertJsonPath('meta.owned_by_colleague.access_requested', true);
        $this->getJson('/api/access-requests')->assertJsonCount(1, 'data.outgoing');

        // Someone else cannot approve it.
        $this->postJson("/api/access-requests/{$requestId}/approve", ['scopes' => ['details']])->assertNotFound();

        $this->as($this->owner);
        $this->getJson('/api/access-requests')
            ->assertJsonCount(1, 'data.incoming')
            ->assertJsonPath('data.incoming.0.user.name', 'Bartek Kelner');
        $this->getJson("/api/contacts/{$this->contact->id}")->assertJsonCount(1, 'data.access_requests');
        $this->postJson("/api/access-requests/{$requestId}/approve", ['scopes' => ['details', 'reservations']])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/access-requests/{$requestId}/decline")->assertStatus(409);

        $this->as($this->colleague);
        $this->getJson('/api/contacts/lookup?email=gosc@firma.pl')
            ->assertJsonPath('data.id', $this->contact->id)
            ->assertJsonPath('data.phone', '+48 600 000 000')
            ->assertJsonPath('data.access.can_manage', false)
            ->assertJsonPath('data.access.scopes', ['details', 'reservations']);
    }

    public function test_scopes_limit_what_a_colleague_sees(): void
    {
        $note = $this->contact->addNote('Lubi stolik przy oknie', $this->owner);
        $emailA = Activity::recordEmail($this->contact, ['message_id' => 'm-1', 'subject' => 'Oferta'], $this->owner);
        $emailB = Activity::recordEmail($this->contact, ['message_id' => 'm-2', 'subject' => 'Faktura'], $this->owner);
        $reservation = Reservation::factory()->for($this->contact)->create(['user_id' => $this->owner->id]);
        $task = Task::factory()->for($this->contact)->create(['user_id' => $this->owner->id]);

        // Share only one e-mail with the colleague.
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['user_id' => $this->colleague->id, 'scopes' => [], 'email_ids' => [$emailA->id]])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Bartek Kelner')
            ->assertJsonPath('data.email_ids', [$emailA->id]);

        $this->as($this->colleague);
        $card = $this->getJson("/api/contacts/{$this->contact->id}")->assertOk();
        $card->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.custom_fields')
            ->assertJsonPath('data.tasks', [])
            ->assertJsonPath('data.recent_notes', [])
            ->assertJsonPath('data.upcoming_reservations', [])
            ->assertJsonMissingPath('data.shares');
        $timeline = collect($this->getJson("/api/contacts/{$this->contact->id}/activities")->json('data'))->pluck('id');
        $this->assertEquals([$emailA->id], $timeline->all());
        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
        $this->getJson("/api/reservations/{$reservation->id}")->assertNotFound();
        $this->getJson('/api/reservations')->assertJsonCount(0, 'data');

        // The whole team gets notes and work; the shares add up.
        $this->as($this->owner);
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['user_id' => null, 'scopes' => ['notes', 'work']])->assertCreated();

        $this->as($this->colleague);
        $timeline = collect($this->getJson("/api/contacts/{$this->contact->id}/activities")->json('data'))->pluck('id');
        $this->assertTrue($timeline->contains($note->id));
        $this->assertTrue($timeline->contains($emailA->id));
        $this->assertFalse($timeline->contains($emailB->id));
        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.can_edit', false);
        $this->getJson('/api/contacts?owner=shared')->assertJsonCount(1, 'data');
        $this->getJson('/api/contacts?owner=mine')->assertJsonCount(0, 'data');

        $this->as($this->third);
        $this->getJson("/api/contacts/{$this->contact->id}")->assertOk()->assertJsonPath('data.access.scopes', ['notes', 'work']);
        $timeline = collect($this->getJson("/api/contacts/{$this->contact->id}/activities")->json('data'))->pluck('id');
        $this->assertFalse($timeline->contains($emailA->id));
    }

    public function test_colleagues_add_their_own_entries_but_cannot_change_the_owners(): void
    {
        $ownersNote = $this->contact->addNote('Notatka opiekuna', $this->owner);
        $ownersTask = Task::factory()->for($this->contact)->create(['user_id' => $this->owner->id]);
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['scopes' => ['details', 'notes', 'emails', 'reservations', 'work']])->assertCreated();

        $this->as($this->colleague);
        $this->patchJson("/api/contacts/{$this->contact->id}", ['name' => 'Zmiana'])->assertForbidden();
        $this->deleteJson("/api/contacts/{$this->contact->id}")->assertForbidden();
        $this->postJson("/api/contacts/{$this->contact->id}/custom-fields", ['label' => 'Alergie', 'value' => 'orzechy'])->assertForbidden();
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['scopes' => ['details']])->assertForbidden();
        $this->patchJson("/api/contacts/{$this->contact->id}/activities/{$ownersNote->id}", ['body' => 'x'])->assertForbidden();
        $this->patchJson("/api/tasks/{$ownersTask->id}", ['title' => 'x'])->assertForbidden();

        $this->postJson("/api/contacts/{$this->contact->id}/notes", ['body' => 'Moja notatka'])->assertCreated();
        $taskId = $this->postJson('/api/tasks', ['title' => 'Moje zadanie', 'contact_id' => $this->contact->id])->assertCreated()->json('data.id');
        $this->patchJson("/api/tasks/{$taskId}", ['is_completed' => true])->assertOk()->assertJsonPath('data.can_edit', true);
        $this->postJson('/api/reservations', ['contact_id' => $this->contact->id, 'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '19:00', 'guests_count' => 4])
            ->assertCreated();

        // The owner sees everything the colleague added and can change it.
        $this->as($this->owner);
        $this->getJson("/api/contacts/{$this->contact->id}")->assertJsonPath('data.notes_count', 2)->assertJsonCount(1, 'data.upcoming_reservations');
        $this->patchJson("/api/tasks/{$taskId}", ['title' => 'Poprawione'])->assertOk();
    }

    public function test_entries_stay_visible_to_their_author_after_the_share_is_removed(): void
    {
        $shareId = $this->postJson("/api/contacts/{$this->contact->id}/shares", ['user_id' => $this->colleague->id, 'scopes' => ['work']])->json('data.id');

        $this->as($this->colleague);
        $taskId = $this->postJson('/api/tasks', ['title' => 'Oferta', 'contact_id' => $this->contact->id])->assertCreated()->json('data.id');

        $this->as($this->owner);
        $this->deleteJson("/api/contacts/{$this->contact->id}/shares/{$shareId}")->assertNoContent();

        $this->as($this->colleague);
        $this->getJson("/api/contacts/{$this->contact->id}")->assertNotFound();
        $this->getJson("/api/tasks/{$taskId}")->assertOk();
    }

    public function test_assigned_tasks_are_visible_to_the_assignee(): void
    {
        $taskId = $this->postJson('/api/tasks', ['title' => 'Wyślij menu', 'contact_id' => $this->contact->id, 'assigned_to' => $this->colleague->id])
            ->assertCreated()->json('data.id');

        $this->as($this->colleague);
        $this->getJson('/api/tasks?assigned_to=me')->assertJsonCount(1, 'data');
        $this->patchJson("/api/tasks/{$taskId}", ['is_completed' => true])->assertOk();
    }

    public function test_transfer_hands_the_client_over(): void
    {
        $this->postJson("/api/contacts/{$this->contact->id}/transfer", ['user_id' => $this->colleague->id])
            ->assertOk()->assertJsonPath('data.owner.name', 'Bartek Kelner');

        // The previous owner keeps full access, but no longer manages it.
        $this->getJson("/api/contacts/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('data.access.can_manage', false)
            ->assertJsonPath('data.phone', '+48 600 000 000');

        $this->as($this->colleague);
        $this->patchJson("/api/contacts/{$this->contact->id}", ['name' => 'Nowy opiekun'])->assertOk();
    }

    public function test_contacts_of_removed_users_are_shared_with_the_team(): void
    {
        $this->owner->delete();

        $this->as($this->colleague);
        $this->getJson("/api/contacts/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('data.owner', null)
            ->assertJsonPath('data.access.can_manage', true);
    }

    public function test_other_restaurants_cannot_be_shared_with(): void
    {
        $stranger = User::factory()->licensed()->create();

        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['user_id' => $stranger->id, 'scopes' => ['details']])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['user_id' => $this->owner->id, 'scopes' => ['details']])
            ->assertStatus(422);
        $this->postJson("/api/contacts/{$this->contact->id}/shares", ['scopes' => []])
            ->assertStatus(422)->assertJsonValidationErrors('scopes');
    }
}
