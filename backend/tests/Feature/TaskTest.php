<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAsExtension($this->user);
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

    public function test_dashboard_summary_has_separate_task_and_reminder_counters(): void
    {
        $base = ['contact_id' => $this->contact->id, 'group_id' => $this->user->group_id];
        Task::factory()->create($base + ['due_date' => now()->subDay()]);
        Task::factory()->create($base + ['due_date' => now()->addDays(3)]);
        Task::factory()->create(); // another restaurant's task must not leak

        Reminder::factory()->for($this->contact)->create(['remind_at' => now()->subHour()]);
        Reminder::factory()->for($this->contact)->create(['remind_at' => now()->subHours(2)]);
        Reminder::factory()->for($this->contact)->create(['remind_at' => now()->addDays(2)]);
        Reminder::factory()->create(['remind_at' => now()->subHour()]); // other group

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Europe/Warsaw') // restaurant timezone by default
            ->assertJsonPath('data.contacts.total', 1)
            ->assertJsonPath('data.tasks.open', 2)
            ->assertJsonPath('data.tasks.overdue', 1)
            ->assertJsonPath('data.tasks.upcoming_week', 1)
            ->assertJsonCount(1, 'data.tasks.lists.overdue')
            ->assertJsonPath('data.reminders.overdue', 2)
            ->assertJsonPath('data.reminders.upcoming_week', 1)
            ->assertJsonCount(2, 'data.reminders.lists.overdue')
            ->assertJsonPath('data.reminders.lists.overdue.0.time_status', 'overdue')
            ->assertJsonStructure(['data' => [
                'contacts' => ['total', 'clients', 'by_category', 'by_status'],
                'tasks' => ['overdue', 'due_today', 'upcoming_week', 'open', 'urgent', 'lists' => ['overdue', 'today', 'upcoming']],
                'reminders' => ['overdue', 'due_today', 'upcoming_week', 'lists' => ['overdue', 'today', 'upcoming']],
                'reservations' => ['today', 'today_guests', 'upcoming'],
                'emails' => ['today'],
            ]]);

        $this->getJson('/api/dashboard/summary?tz=Mars/Base')->assertStatus(422);
    }
}
