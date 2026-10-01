<?php

namespace Tests\Feature\Models;

use App\Enums\ActivityType;
use App\Enums\ReservationStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Reminder;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimelineAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        $this->contact = Contact::factory()->for($this->user->group)->client()->create();
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reminder_time_windows_respect_timezone(): void
    {
        // 22:30 UTC = 00:30 next day in Warsaw (UTC+2 in October).
        Carbon::setTestNow('2026-10-14 22:30:00');
        $make = fn (string $at) => Reminder::factory()->for($this->contact)->create(['remind_at' => $at]);

        $overdue = $make('2026-10-14 20:00:00');
        $todayWarsaw = $make('2026-10-15 15:00:00');   // 17:00 Warsaw, same local day
        $inFiveDays = $make('2026-10-20 10:00:00');
        $later = $make('2026-11-30 10:00:00');
        $make('2026-10-13 10:00:00')->update(['is_done' => true]);

        $this->assertSame([$overdue->id], Reminder::overdue()->pluck('id')->all());
        $this->assertSame([$todayWarsaw->id], Reminder::dueToday('Europe/Warsaw')->pluck('id')->all());
        $this->assertSame([$inFiveDays->id], Reminder::upcoming('Europe/Warsaw')->pluck('id')->all());

        // In UTC the 15:00 reminder is "tomorrow" -> upcoming, not today.
        $this->assertSame([], Reminder::dueToday('UTC')->pluck('id')->all());

        $this->assertSame('overdue', $overdue->timeStatus('Europe/Warsaw'));
        $this->assertSame('today', $todayWarsaw->timeStatus('Europe/Warsaw'));
        $this->assertSame('upcoming', $inFiveDays->timeStatus('Europe/Warsaw'));
        $this->assertSame('later', $later->timeStatus('Europe/Warsaw'));
        $this->assertNotNull(Reminder::where('is_done', true)->first()->done_at);
    }

    public function test_urgent_tasks_are_high_priority_or_due_by_end_of_today(): void
    {
        $high = Task::factory()->for($this->contact)->create(['priority' => 'high', 'due_date' => now()->addDays(5)]);
        $dueToday = Task::factory()->for($this->contact)->create(['due_date' => now()->addMinutes(30)]);
        Task::factory()->for($this->contact)->create(['due_date' => now()->addDays(5)]);

        $this->assertEqualsCanonicalizing([$high->id, $dueToday->id], Task::urgent('UTC')->pluck('id')->all());
    }

    public function test_internal_task_without_contact(): void
    {
        $task = $this->user->tasks()->create(['title' => 'Rozesłać grafik kelnerów', 'type' => 'internal']);

        $this->assertNull($task->contact_id);
        $this->assertSame($this->user->group_id, $task->group_id);
    }

    public function test_notes_emails_and_system_events_build_the_contact_timeline(): void
    {
        Carbon::setTestNow('2026-10-14 10:42:00');
        Activity::recordEmail($this->contact, [
            'message_id' => '18f2c0ffee',
            'subject' => 'Zapytanie o wigilię firmową na 20 osób',
            'snippet' => 'Proszę o przesłanie menu...',
            'from' => $this->contact->email,
        ]);

        Carbon::setTestNow('2026-10-14 11:00:00');
        $this->contact->addNote('Zaproponowałem rabat 5%.');

        Carbon::setTestNow('2026-10-14 11:05:00');
        $reservation = Reservation::factory()->for($this->contact)->create([
            'reservation_date' => '2026-12-18', 'reservation_time' => '19:00', 'guests_count' => 20, 'status' => 'pending',
        ]);

        Carbon::setTestNow('2026-10-14 11:10:00');
        $reservation->update(['status' => ReservationStatus::Confirmed]);

        Carbon::setTestNow('2026-10-14 11:15:00');
        $task = Task::factory()->for($this->contact)->create();
        $task->update(['is_completed' => true]);

        $timeline = $this->contact->timeline()->get();

        $this->assertSame(
            ['system:task.completed', 'system:reservation.status_changed', 'system:reservation.created', 'note:', 'email:'],
            $timeline->map(fn ($a) => $a->type->value.':'.$a->event)->all(),
        );
        $this->assertSame(['from' => 'pending', 'to' => 'confirmed'], array_intersect_key($timeline[1]->meta, ['from' => 1, 'to' => 1]));
        $this->assertSame($this->user->id, $timeline[3]->author->id);
        $this->assertSame('18f2c0ffee', $timeline[4]->meta['message_id']);
        $this->assertInstanceOf(Reservation::class, $timeline[2]->subject);
        $this->assertTrue($timeline->every(fn ($a) => $a->group_id === $this->contact->group_id));

        // Feeds the "Ostatnia aktywność" column.
        $this->assertSame('2026-10-14 11:15:00', $this->contact->fresh()->last_activity_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Activity::ofType(ActivityType::Email)->count());
    }

    public function test_custom_fields_are_typed_and_inherit_the_contacts_group(): void
    {
        $allergy = $this->contact->customFields()->create(['label' => 'Alergie', 'value' => 'orzechy, gluten']);
        $guests = $this->contact->customFields()->create(['label' => 'Średnia liczba gości', 'type' => 'number', 'value' => '6']);

        $this->assertSame('alergie', $allergy->key);
        $this->assertSame('srednia_liczba_gosci', $guests->key);
        $this->assertSame(6, $guests->typedValue());
        $this->assertSame($this->contact->group_id, $allergy->group_id);
        $this->assertSame(['Alergie', 'Średnia liczba gości'], $this->contact->customFields()->pluck('label')->all());
    }

    public function test_reservation_reminder_is_attached_to_the_guest(): void
    {
        $reservation = Reservation::factory()->for($this->contact)->create();
        $reminder = $reservation->reminders()->create([
            'type' => 'reservation', 'title' => 'Potwierdzić tort', 'remind_at' => now()->addHour(),
        ]);

        $this->assertSame($this->contact->id, $reminder->contact_id);
        $this->assertTrue($this->contact->upcomingReservations()->get()->contains($reservation));
    }
}
