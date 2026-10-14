<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationWindowsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'Europe/Warsaw'));
        $this->user = User::factory()->licensed()->create();
        $this->actingAsExtension($this->user);
        $this->contact = Contact::factory()->for($this->user->group)->create(['user_id' => $this->user->id, 'name' => 'Jan']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(string $date, string $time = '19:00', array $extra = []): int
    {
        return $this->postJson('/api/reservations', $extra + [
            'contact_id' => $this->contact->id, 'reservation_date' => $date, 'reservation_time' => $time, 'guests_count' => 4,
        ])->assertCreated()->json('data.id');
    }

    public function test_dashboard_and_list_split_bookings_like_tasks(): void
    {
        $overdue = Reservation::factory()->for($this->contact)->create(['reservation_date' => '2026-10-12', 'status' => 'confirmed', 'user_id' => $this->user->id]);
        Reservation::factory()->for($this->contact)->create(['reservation_date' => '2026-10-11', 'status' => 'completed']);
        $today = $this->book('2026-10-14', '13:00');
        $week = $this->book('2026-10-20');
        $this->book('2026-11-30');

        $summary = $this->getJson('/api/dashboard/summary')->assertOk()->json('data.reservations');
        $this->assertSame([1, 1, 1], [$summary['overdue'], $summary['due_today'], $summary['upcoming_week']]);
        $this->assertSame($overdue->id, $summary['lists']['overdue'][0]['id']);
        $this->assertSame('today', $summary['lists']['today'][0]['time_status']);

        $this->getJson('/api/reservations?window=today')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $today);
        $this->getJson('/api/reservations?window=upcoming')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $week);
        $this->getJson('/api/reservations?window=overdue')->assertJsonCount(1, 'data');
        $this->getJson('/api/reservations?window=done')->assertJsonCount(1, 'data');
        $this->getJson('/api/reservations?search=Jan&window=open')->assertJsonCount(3, 'data');
    }

    public function test_a_booking_gets_a_reminder_that_follows_it(): void
    {
        $id = $this->book('2026-10-20', '19:00');
        $reminder = Reminder::query()->where('reservation_id', $id)->firstOrFail();
        $this->assertTrue($reminder->auto_reservation);
        $this->assertSame($this->user->id, $reminder->user_id);
        // 24 h before 19:00 Warsaw time (CEST, UTC+2).
        $this->assertSame('2026-10-19 17:00', $reminder->remind_at->utc()->format('Y-m-d H:i'));

        $this->patchJson("/api/reservations/{$id}", ['reservation_date' => '2026-10-22'])->assertOk()
            ->assertJsonPath('data.auto_reminder.remind_at', '2026-10-21T17:00:00+00:00');
        $this->patchJson("/api/reservations/{$id}", ['status' => 'cancelled'])->assertOk();
        $this->assertTrue($reminder->fresh()->is_done);

        // Turned off in preferences: no reminder.
        $this->patchJson('/api/me/preferences', ['reservations' => ['auto_reminder' => false]])->assertOk();
        $other = $this->book('2026-10-25');
        $this->assertFalse(Reminder::query()->where('reservation_id', $other)->exists());
    }

    public function test_preferences_accept_the_new_settings(): void
    {
        $this->patchJson('/api/me/preferences', [
            'default_task_time' => '08:30', 'default_task_priority' => 'high', 'default_reminder_days' => 2,
            'reservations' => ['default_time' => '18:00', 'default_guests' => 4, 'duration_minutes' => 150, 'reminder_hours_before' => 3],
            'calendar' => ['include_tasks' => false],
            'google' => ['calendar_sync_reservations' => true],
        ])->assertOk()
            ->assertJsonPath('data.reservations.duration_minutes', 150)
            ->assertJsonPath('data.reservations.auto_reminder', true)
            ->assertJsonPath('data.calendar.include_reminders', true)
            ->assertJsonPath('data.default_task_priority', 'high');

        $this->patchJson('/api/me/preferences', ['default_task_priority' => 'urgent'])->assertStatus(422);

        $id = $this->book('2026-10-20');
        $this->putJson("/api/calendar-events/reservation/{$id}", ['external_id' => 'evt-1'])->assertOk();
        $this->getJson("/api/reservations/{$id}")->assertJsonPath('data.calendar_event_id', 'evt-1');
    }

    public function test_opened_emails_get_their_real_date_and_last_activity_follows(): void
    {
        // Opened in Gmail without a date: logged "now".
        $this->postJson("/api/contacts/{$this->contact->id}/emails", ['message_id' => 'm-1', 'subject' => 'Wesele'])->assertCreated();
        $this->assertSame('2026-10-14', $this->contact->fresh()->last_activity_at->setTimezone('Europe/Warsaw')->toDateString());

        // The Gmail import knows the real send time and corrects it.
        $this->postJson("/api/contacts/{$this->contact->id}/emails/import", ['emails' => [
            ['message_id' => 'm-1', 'subject' => 'Wesele', 'sent_at' => '2026-10-10T08:00:00Z'],
            ['message_id' => 'm-0', 'subject' => 'Pierwszy', 'sent_at' => '2026-10-01T08:00:00Z'],
        ]])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.imported', 1);

        $activity = Activity::query()->where('meta->message_id', 'm-1')->first();
        $this->assertSame('2026-10-10 08:00', $activity->occurred_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-10 08:00', $this->contact->fresh()->last_activity_at->utc()->format('Y-m-d H:i'));

        // Opening it again with the real date changes nothing.
        $this->postJson("/api/contacts/{$this->contact->id}/emails", ['message_id' => 'm-1', 'sent_at' => '2026-10-10T08:00:00Z'])->assertOk();
    }
}
