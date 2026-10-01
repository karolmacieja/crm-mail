<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_full_crud_cycle(): void
    {
        $id = $this->postJson('/api/contacts', [
            'email' => '  Client@Acme.COM ',
            'name' => 'Client One',
            'phone' => '+48 600 100 200',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'client@acme.com')
            ->assertJsonPath('data.status', 'lead')
            ->json('data.id');

        $this->getJson("/api/contacts/{$id}")->assertOk()->assertJsonPath('data.tasks', []);

        $this->patchJson("/api/contacts/{$id}", ['status' => 'customer'])
            ->assertOk()
            ->assertJsonPath('data.status', 'customer')
            ->assertJsonPath('data.name', 'Client One');

        $this->getJson('/api/contacts?search=acme&status=customer')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/contacts/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('contacts', ['id' => $id]);
    }

    public function test_validation_errors(): void
    {
        Contact::factory()->for($this->user)->create(['email' => 'dup@acme.com']);

        $this->postJson('/api/contacts', ['email' => 'DUP@acme.com', 'status' => 'vip', 'phone' => 'call me'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'status', 'phone']);
    }

    public function test_same_email_allowed_for_different_users(): void
    {
        Contact::factory()->create(['email' => 'shared@acme.com']);

        $this->postJson('/api/contacts', ['email' => 'shared@acme.com'])->assertCreated();
    }

    public function test_lookup_by_email_returns_contact_with_tasks_or_null(): void
    {
        $contact = Contact::factory()->for($this->user)->create(['email' => 'sender@acme.com']);
        Task::factory()->for($contact)->create(['user_id' => $this->user->id]);

        $this->getJson('/api/contacts/lookup?email=Sender@Acme.com')
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.open_tasks_count', 1)
            ->assertJsonCount(1, 'data.tasks');

        $this->getJson('/api/contacts/lookup?email=unknown@acme.com')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_users_cannot_access_each_others_contacts(): void
    {
        $foreign = Contact::factory()->create();

        $this->getJson("/api/contacts/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/contacts/{$foreign->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/contacts/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/contacts/lookup?email={$foreign->email}")->assertExactJson(['data' => null]);
        $this->getJson('/api/contacts')->assertJsonCount(0, 'data');
    }
}
