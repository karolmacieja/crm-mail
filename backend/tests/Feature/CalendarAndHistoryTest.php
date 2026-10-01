<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        $this->contact = Contact::factory()->for($this->user->group)->create(['name' => 'Jan Kowalski', 'email' => 'jan@xyz.pl']);
        $this->actingAsExtension($this->user);
    }

    public function test_preferences_have_defaults_and_merge_partial_updates(): void
    {
        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.default_reminder_time', '09:00')
            ->assertJsonPath('data.google.email_history', true)
            ->assertJsonPath('data.calendar_feed_url', null);

        $this->patchJson('/api/me/preferences', ['calendar' => ['alarm_minutes' => 30], 'google' => ['calendar_sync' => true]])
            ->assertOk()
            ->assertJsonPath('data.calendar.alarm_minutes', 30)
            ->assertJsonPath('data.calendar.include_reservations', true)
            ->assertJsonPath('data.google.calendar_sync', true);

        $this->patchJson('/api/me/preferences', ['default_reminder_time' => '25:00'])->assertJsonValidationErrors('default_reminder_time');
        $this->patchJson('/api/me/preferences', ['google' => ['hack' => true]])->assertJsonValidationErrors('google');
    }

    public function test_private_calendar_feed_contains_my_tasks_reminders_and_reservations(): void
    {
        $colleague = User::factory()->for($this->user->group)->create();
        $base = ['contact_id' => $this->contact->id, 'group_id' => $this->user->group_id];

        Task::factory()->create($base + ['title' => 'Moje; zadanie, z przecinkiem', 'user_id' => $this->user->id, 'due_date' => now()->addDay()]);
        Task::factory()->create($base + ['title' => 'Przypisane mnie', 'user_id' => $colleague->id, 'assigned_to' => $this->user->id, 'due_date' => now()->addDays(2)]);
        Task::factory()->create($base + ['title' => 'Cudze zadanie', 'user_id' => $colleague->id, 'due_date' => now()->addDay()]);
        Task::factory()->completed()->create($base + ['title' => 'Zrobione', 'user_id' => $this->user->id, 'due_date' => now()->addDay()]);
        Reminder::factory()->for($this->contact)->create(['title' => 'Zadzwonić', 'user_id' => $this->user->id, 'remind_at' => now()->addHours(5)]);
        Reservation::factory()->for($this->contact)->create(['guests_count' => 20, 'reservation_date' => now()->addDays(3)->toDateString(), 'reservation_time' => '19:00']);

        $url = $this->postJson('/api/me/calendar-feed')->assertOk()->json('data.url');
        $this->assertStringEndsWith('.ics', $url);

        $ics = $this->get(parse_url($url, PHP_URL_PATH))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:✔ Moje\; zadanie\, z przecinkiem', $ics);
        $this->assertStringContainsString('Przypisane mnie', $ics);
        $this->assertStringNotContainsString('Cudze zadanie', $ics);
        $this->assertStringNotContainsString('Zrobione', $ics);
        $this->assertStringContainsString('SUMMARY:🔔 Zadzwonić', $ics);
        $this->assertStringContainsString('Jan Kowalski', $ics);
        $this->assertStringContainsString('TRIGGER:-PT15M', $ics);
        $this->assertSame(4, substr_count($ics, 'BEGIN:VEVENT'));

        // Reservations can be switched off; rotating the token kills the old URL.
        $this->patchJson('/api/me/preferences', ['calendar' => ['include_reservations' => false]]);
        $this->assertSame(3, substr_count($this->get(parse_url($url, PHP_URL_PATH))->getContent(), 'BEGIN:VEVENT'));

        $this->postJson('/api/me/calendar-feed');
        $this->get(parse_url($url, PHP_URL_PATH))->assertNotFound();
    }

    public function test_feed_is_empty_without_license_and_404_when_disabled(): void
    {
        $path = parse_url($this->postJson('/api/me/calendar-feed')->json('data.url'), PHP_URL_PATH);
        Task::factory()->create(['contact_id' => $this->contact->id, 'group_id' => $this->user->group_id, 'user_id' => $this->user->id, 'due_date' => now()->addDay()]);

        $this->user->license->update(['expires_at' => now()->subMinute()]);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $this->get($path)->assertOk()->getContent());

        $this->deleteJson('/api/me/calendar-feed')->assertNoContent();
        $this->get($path)->assertNotFound();
    }

    public function test_long_lines_are_folded(): void
    {
        $long = str_repeat('Bardzo długi tytuł zadania ', 10);
        Task::factory()->create(['contact_id' => $this->contact->id, 'group_id' => $this->user->group_id, 'user_id' => $this->user->id, 'title' => $long, 'due_date' => now()->addDay()]);
        $ics = $this->get(parse_url($this->postJson('/api/me/calendar-feed')->json('data.url'), PHP_URL_PATH))->getContent();

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_email_history_import_skips_duplicates_and_upgrades_placeholders(): void
    {
        $base = "/api/contacts/{$this->contact->id}";
        $this->postJson("{$base}/emails", ['message_id' => 'thread:t-1', 'thread_id' => 't-1', 'subject' => 'Wigilia']);
        $this->postJson("{$base}/emails", ['message_id' => 'm-already', 'thread_id' => 't-2']);

        $history = [
            ['message_id' => 'm-1', 'thread_id' => 't-1', 'subject' => 'Wigilia', 'snippet' => 'Pierwszy mail', 'direction' => 'in', 'sent_at' => '2025-11-02T10:00:00Z'],
            ['message_id' => 'm-2', 'thread_id' => 't-1', 'subject' => 'Re: Wigilia', 'direction' => 'out', 'sent_at' => '2025-11-03T09:00:00Z'],
            ['message_id' => 'm-already', 'thread_id' => 't-2'],
            ['message_id' => 'm-3', 'thread_id' => 't-3', 'subject' => 'Stara rezerwacja', 'sent_at' => '2024-05-01T18:00:00Z'],
        ];

        $this->postJson("{$base}/emails/import", ['emails' => $history])
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertNotNull($this->contact->fresh()->email_history_synced_at);
        $emails = $this->getJson("{$base}/activities?type=email&per_page=50")->assertJsonCount(4, 'data')->json('data');
        // Ordered by the real send date; the oldest email is last.
        $this->assertSame('Stara rezerwacja', end($emails)['title']);

        // Running it again changes nothing.
        $this->postJson("{$base}/emails/import", ['emails' => $history])->assertJsonPath('data.imported', 0)->assertJsonPath('data.skipped', 4);
        $this->getJson($base)->assertJsonPath('data.email_history_synced_at', fn ($v) => $v !== null);
    }

    public function test_notes_show_on_the_profile_and_can_be_edited_by_their_author(): void
    {
        $base = "/api/contacts/{$this->contact->id}";
        foreach (['Pierwsza', 'Druga', 'Trzecia', 'Czwarta'] as $body) {
            $this->postJson("{$base}/notes", ['body' => $body]);
        }

        $profile = $this->getJson($base)->assertJsonPath('data.notes_count', 4)->assertJsonCount(3, 'data.recent_notes');
        $noteId = $profile->json('data.recent_notes.0.id');

        $this->patchJson("{$base}/activities/{$noteId}", ['body' => 'Czwarta (poprawiona)'])->assertOk()->assertJsonPath('data.body', 'Czwarta (poprawiona)');

        $this->actingAsExtension(User::factory()->for($this->user->group)->licensed()->create());
        $this->patchJson("{$base}/activities/{$noteId}", ['body' => 'Nie moja'])->assertForbidden();
    }

    public function test_calendar_event_links_are_per_person(): void
    {
        $task = Task::factory()->create(['contact_id' => $this->contact->id, 'group_id' => $this->user->group_id, 'user_id' => $this->user->id]);

        $this->putJson("/api/calendar-events/task/{$task->id}", ['external_id' => 'gcal-1'])->assertOk();
        $this->putJson("/api/calendar-events/task/{$task->id}", ['external_id' => 'gcal-2'])->assertOk();
        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('data.calendar_event_id', 'gcal-2');

        $this->actingAsExtension(User::factory()->for($this->user->group)->licensed()->create());
        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('data.calendar_event_id', null);

        $foreign = Task::factory()->create();
        $this->putJson("/api/calendar-events/task/{$foreign->id}", ['external_id' => 'x'])->assertNotFound();
    }
}
