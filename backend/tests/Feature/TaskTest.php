<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        $this->contact = Contact::factory()->for($this->user->group)->create();
        Sanctum::actingAs($this->user);
    }

    public function test_create_complete_and_delete_task(): void
    {
        $id = $this->postJson('/api/tasks', [
            'contact_id' => $this->contact->id,
            'title' => 'Send proposal',
            'due_date' => now()->addDay()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonPath('data.contact.email', $this->contact->email)
            ->json('data.id');

        $this->patchJson("/api/tasks/{$id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.is_overdue', false);
        $this->assertNotNull(Task::find($id)->completed_at);

        $this->patchJson("/api/tasks/{$id}", ['is_completed' => false]);
        $this->assertNull(Task::find($id)->completed_at);

        $this->deleteJson("/api/tasks/{$id}")->assertNoContent();
    }

    public function test_cannot_attach_task_to_foreign_contact(): void
    {
        $foreign = Contact::factory()->create();

        $this->postJson('/api/tasks', ['contact_id' => $foreign->id, 'title' => 'Sneaky'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact_id');
    }

    public function test_cannot_touch_foreign_tasks(): void
    {
        $foreign = Task::factory()->create();

        $this->patchJson("/api/tasks/{$foreign->id}", ['is_completed' => true])->assertNotFound();
        $this->deleteJson("/api/tasks/{$foreign->id}")->assertNotFound();
    }

    public function test_status_filters(): void
    {
        $base = ['contact_id' => $this->contact->id, 'user_id' => $this->user->id];
        Task::factory()->create($base + ['due_date' => now()->subDay()]);
        Task::factory()->create($base + ['due_date' => now()->addDays(2)]);
        Task::factory()->completed()->create($base);

        $this->getJson('/api/tasks')->assertJsonCount(2, 'data');
        $this->getJson('/api/tasks?status=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/tasks?status=completed')->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?status=all')->assertJsonCount(3, 'data');
    }

    public function test_dashboard_summary(): void
    {
        $base = ['contact_id' => $this->contact->id, 'user_id' => $this->user->id];
        Task::factory()->create($base + ['due_date' => now()->subDay()]);
        Task::factory()->create($base + ['due_date' => now()->addDays(3)]);
        Task::factory()->create(); // other user's task must not leak

        $this->getJson('/api/dashboard/summary?tz=Europe/Warsaw')
            ->assertOk()
            ->assertJsonPath('data.contacts.total', 1)
            ->assertJsonPath('data.tasks.open', 2)
            ->assertJsonPath('data.tasks.overdue', 1)
            ->assertJsonPath('data.tasks.upcoming_week', 1)
            ->assertJsonCount(1, 'data.reminders.overdue')
            ->assertJsonCount(1, 'data.reminders.upcoming')
            ->assertJsonPath('data.timezone', 'Europe/Warsaw');

        $this->getJson('/api/dashboard/summary?tz=Mars/Base')->assertStatus(422);
    }
}
