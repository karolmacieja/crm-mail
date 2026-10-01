<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Group;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extension endpoints added for GastroFlowx: reservations, reminders,
 * custom fields, timeline, categories, team.
 */
class CrmApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $contact;

    private Contact $foreignContact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        $this->contact = Contact::factory()->for($this->user->group)->client()->create(['email' => 'jan@xyz.pl']);
        $this->foreignContact = Contact::factory()->create();
        $this->actingAsExtension($this->user);
    }

    public function test_reservation_crud_writes_the_timeline(): void
    {
        $id = $this->postJson('/api/reservations', [
            'contact_id' => $this->contact->id,
            'reservation_date' => now()->addDays(10)->toDateString(),
            'reservation_time' => '19:00',
            'guests_count' => 20,
            'occasion' => 'Wigilia firmowa',
            'source_email_id' => '18f2abc',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reservation_time', '19:00')
            ->assertJsonPath('data.is_upcoming', true)
            ->json('data.id');

        $this->patchJson("/api/reservations/{$id}", ['status' => 'confirmed'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->getJson('/api/reservations?upcoming=1')->assertJsonCount(1, 'data');

        $this->getJson("/api/contacts/{$this->contact->id}/activities")
            ->assertOk()
            ->assertJsonPath('data.0.event', 'reservation.status_changed')
            ->assertJsonPath('data.1.event', 'reservation.created')
            ->assertJsonPath('data.1.subject_type', 'reservation');

        $this->postJson('/api/reservations', [
            'contact_id' => $this->foreignContact->id, 'reservation_date' => '2026-12-01', 'reservation_time' => '19:00', 'guests_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('contact_id');

        $this->deleteJson("/api/reservations/{$id}")->assertNoContent();
    }

    public function test_reminders_windows_and_validation(): void
    {
        $this->postJson('/api/reminders', ['title' => 'Odpowiedzieć na wycenę', 'type' => 'email', 'remind_at' => now()->subHour()->toIso8601String(), 'contact_id' => $this->contact->id])
            ->assertCreated()->assertJsonPath('data.time_status', 'overdue');
        $this->postJson('/api/reminders', ['title' => 'Za tydzień', 'remind_at' => now()->addDays(3)->toIso8601String()])
            ->assertCreated()->assertJsonPath('data.time_status', 'upcoming');

        $this->getJson('/api/reminders?window=overdue')->assertJsonCount(1, 'data');
        $this->getJson('/api/reminders?window=upcoming')->assertJsonCount(1, 'data');
        $this->getJson('/api/reminders?type=email')->assertJsonCount(1, 'data');
        $this->getJson('/api/reminders?search=wycen')->assertJsonCount(1, 'data');

        $reservation = Reservation::factory()->create(); // other group
        $this->postJson('/api/reminders', ['title' => 'x', 'remind_at' => now()->toIso8601String(), 'reservation_id' => $reservation->id])
            ->assertStatus(422)->assertJsonValidationErrors('reservation_id');
    }

    public function test_tasks_types_assignee_and_filters(): void
    {
        $colleague = User::factory()->for($this->user->group)->create();
        $stranger = User::factory()->create();

        $this->postJson('/api/tasks', ['title' => 'Rozesłać grafik', 'type' => 'internal', 'assigned_to' => $colleague->id])
            ->assertCreated()->assertJsonPath('data.contact_id', null)->assertJsonPath('data.assignee.id', $colleague->id);
        $this->postJson('/api/tasks', ['title' => 'Oferta', 'type' => 'offer', 'priority' => 'high', 'contact_id' => $this->contact->id, 'due_date' => now()->addDays(4)->toIso8601String()])
            ->assertCreated();
        $this->postJson('/api/tasks', ['title' => 'x', 'assigned_to' => $stranger->id])->assertJsonValidationErrors('assigned_to');

        $this->getJson('/api/tasks?type=internal')->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?urgent=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Oferta');
        $this->getJson("/api/tasks?assigned_to={$colleague->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?search=graf')->assertJsonCount(1, 'data');
    }

    public function test_custom_fields_notes_and_emails_on_the_client_card(): void
    {
        $base = "/api/contacts/{$this->contact->id}";

        $fieldId = $this->postJson("{$base}/custom-fields", ['label' => 'Alergie', 'value' => 'orzechy'])
            ->assertCreated()->assertJsonPath('data.key', 'alergie')->json('data.id');
        $this->postJson("{$base}/custom-fields", ['label' => 'Alergie', 'value' => 'x'])->assertStatus(422);
        $this->postJson("{$base}/custom-fields", ['label' => 'Goście', 'type' => 'number', 'value' => 'dużo'])->assertJsonValidationErrors('value');
        $this->patchJson("{$base}/custom-fields/{$fieldId}", ['value' => 'orzechy, gluten'])->assertOk()->assertJsonPath('data.value', 'orzechy, gluten');

        $this->postJson("{$base}/notes", ['body' => 'Rabat 5% do końca miesiąca.'])->assertCreated()->assertJsonPath('data.author.id', $this->user->id);

        $email = ['message_id' => '18f2c0ffee', 'subject' => 'Zapytanie o wigilię', 'snippet' => 'Proszę o menu', 'direction' => 'in'];
        $this->postJson("{$base}/emails", $email)->assertCreated();
        $this->postJson("{$base}/emails", $email)->assertOk(); // idempotent per Gmail message id

        $this->getJson("{$base}/activities?type=email")->assertJsonCount(1, 'data');
        $this->getJson("{$base}/activities")->assertJsonCount(2, 'data');

        $this->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.custom_fields.0.label', 'Alergie')
            ->assertJsonPath('data.last_activity.type', 'email')
            ->assertJsonStructure(['data' => ['category', 'tasks', 'reminders', 'upcoming_reservations', 'open_tasks_count']]);

        // Another restaurant's contact is invisible on every nested route.
        $this->postJson("/api/contacts/{$this->foreignContact->id}/notes", ['body' => 'x'])->assertNotFound();
    }

    public function test_thread_placeholder_and_real_message_make_one_timeline_entry(): void
    {
        $base = "/api/contacts/{$this->contact->id}/emails";

        // Dashboard (inbox row): only the thread id is known.
        $this->postJson($base, ['message_id' => 'thread:t-1', 'thread_id' => 't-1', 'subject' => 'Wigilia'])->assertCreated();
        $this->postJson($base, ['message_id' => 'thread:t-1', 'thread_id' => 't-1', 'subject' => 'Wigilia'])->assertOk();

        // Gmail thread view: the real message id replaces the placeholder.
        $this->postJson($base, ['message_id' => '18f2', 'thread_id' => 't-1', 'subject' => 'Wigilia', 'snippet' => 'Proszę o menu'])
            ->assertOk()
            ->assertJsonPath('data.meta.message_id', '18f2')
            ->assertJsonPath('data.body', 'Proszę o menu');

        // Later dashboard clicks on the same thread add nothing.
        $this->postJson($base, ['message_id' => 'thread:t-1', 'thread_id' => 't-1'])->assertOk();
        // A newer message in the same thread is a new entry.
        $this->postJson($base, ['message_id' => '18f3', 'thread_id' => 't-1', 'subject' => 'Re: Wigilia'])->assertCreated();

        $this->getJson("/api/contacts/{$this->contact->id}/activities?type=email")->assertJsonCount(2, 'data');
    }

    public function test_batch_lookup_returns_only_known_senders_of_the_group(): void
    {
        $this->postJson('/api/contacts/lookup-many', ['emails' => ['JAN@xyz.pl', 'nieznany@x.pl', $this->foreignContact->email]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'jan@xyz.pl');

        $this->postJson('/api/contacts/lookup-many', ['emails' => ['nie-email']])->assertJsonValidationErrors('emails.0');
    }

    public function test_contacts_filters_categories_and_team(): void
    {
        $vip = $this->user->group->contactCategories()->where('slug', 'vip')->first();
        $this->contact->update(['category_id' => $vip->id]);
        Contact::factory()->for($this->user->group)->create(['is_client' => false]);

        $this->getJson('/api/contacts?is_client=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.category.slug', 'vip');
        $this->getJson('/api/contacts?is_client=0')->assertJsonCount(1, 'data');
        $this->getJson('/api/contacts?category=vip')->assertJsonCount(1, 'data');
        $this->getJson("/api/contacts?category={$vip->id}")->assertJsonCount(1, 'data');

        $this->postJson('/api/contacts', [
            'email' => 'nowy@firma.pl', 'is_client' => true, 'company' => 'Firma', 'category_id' => $vip->id,
            'custom_fields' => [['label' => 'NIP', 'value' => '123']],
        ])->assertCreated()->assertJsonPath('data.custom_fields.0.label', 'NIP');

        // GroupScope hides other groups' categories even here, hence withoutGlobalScopes().
        $foreignCategory = Group::factory()->create()->contactCategories()->withoutGlobalScopes()->first();
        $this->postJson('/api/contacts', ['email' => 'x@y.pl', 'category_id' => $foreignCategory->id])->assertJsonValidationErrors('category_id');

        $this->getJson('/api/categories')->assertJsonCount(3, 'data')->assertJsonPath('data.1.contacts_count', 2);
        $this->getJson('/api/team')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_me', true);
    }
}
