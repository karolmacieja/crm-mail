<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Group;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Demo data matching the GastroFlowx mockup (Jan Kowalski / Firma XYZ, Anna Nowak / VIP, ...).
 * Local development only. All demo accounts use the password "password".
 * Due dates are relative to "now" so the overdue / today / 7-day buckets are always populated.
 *
 *   master@gastroflowx.test  – Master Admin (web panel)
 *   manager@roma.test        – manager of "Restauracja Roma"
 *   kelner@roma.test         – staff of "Restauracja Roma" (Gmail extension)
 *   manager@sushi.test       – another tenant, to see isolation
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $password = 'password';

        $master = User::create(['name' => 'Karol Macieja', 'email' => 'master@gastroflowx.test', 'password' => $password]);
        $master->role = UserRole::MasterAdmin;
        $master->save();

        $roma = Group::create(['name' => 'Restauracja Roma', 'contact_email' => 'kontakt@roma.test']);
        $sushi = Group::create(['name' => 'Sushi Bar Kioto', 'contact_email' => 'kontakt@sushi.test']);

        $manager = $this->member($roma, 'Marta Wiśniewska', 'manager@roma.test', UserRole::Manager, $password);
        $waiter = $this->member($roma, 'Piotr Kelner', 'kelner@roma.test', UserRole::Staff, $password);
        $sushiManager = $this->member($sushi, 'Kenji Sato', 'manager@sushi.test', UserRole::Manager, $password);

        $romaLicense = $roma->licenses()->create(['plan' => 'pro', 'seats' => 5, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addYear()]);
        $romaLicense->assignTo($manager, $master);
        $romaLicense->assignTo($waiter, $master);
        $sushi->licenses()->create(['seats' => 2, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addMonths(3)])
            ->assignTo($sushiManager, $master);

        app(Tenancy::class)->runAs($roma, fn () => $this->seedRoma($roma, $manager, $waiter));
        app(Tenancy::class)->runAs($sushi, function () use ($sushi, $sushiManager) {
            $sushiManager->contacts()->create([
                'email' => 'tanaka@firma.jp', 'name' => 'Hiro Tanaka', 'is_client' => true, 'status' => 'customer',
                'category_id' => $sushi->contactCategories()->where('slug', 'vip')->value('id'),
            ]);
        });

        $this->command?->info('Demo data ready. Log in with e.g. manager@roma.test / password.');
    }

    private function member(Group $group, string $name, string $email, UserRole $role, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->group_id = $group->id;
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function seedRoma(Group $roma, User $manager, User $waiter): void
    {
        $category = fn (string $slug) => $roma->contactCategories()->where('slug', $slug)->value('id');
        $today = Carbon::now('Europe/Warsaw');

        // --- Jan Kowalski, Firma XYZ (B2B): Christmas party enquiry -----------------
        $jan = $manager->contacts()->create([
            'email' => 'jan@xyz.pl', 'name' => 'Jan Kowalski', 'company' => 'Firma XYZ Sp. z o.o.',
            'status' => 'prospect', 'is_client' => true, 'category_id' => $category('b2b'),
        ]);
        $jan->customFields()->create(['label' => 'NIP', 'value' => '525-000-00-00']);

        Activity::recordEmail($jan, [
            'message_id' => 'demo-msg-jan-1',
            'thread_id' => 'demo-thread-jan',
            'subject' => 'Zapytanie o wigilię firmową na 20 osób.',
            'snippet' => 'Dzień dobry, proszę o przesłanie menu wigilijnego dla 20 osób...',
            'from' => 'jan@xyz.pl',
            'sent_at' => $today->copy()->setTime(10, 42)->utc(),
        ]);
        $jan->addNote('Klient B2B, zaproponowałem rabat 5% przy podpisaniu umowy do końca miesiąca.', $manager);

        $wigilia = $jan->reservations()->create([
            'reservation_date' => $today->copy()->setDate($today->year, 12, 18)->toDateString(),
            'reservation_time' => '19:00', 'guests_count' => 20, 'status' => 'pending',
            'table_label' => 'Sala bankietowa', 'occasion' => 'Wigilia firmowa', 'source_email_id' => 'demo-msg-jan-1',
        ]);

        $jan->reminders()->create([
            'type' => 'email', 'title' => 'Odpowiedzieć na wycenę (Firma XYZ)',
            'remind_at' => $today->copy()->subDay()->setTime(16, 0)->utc(),
            'source_email_id' => 'demo-msg-jan-1', 'source_email_subject' => 'Zapytanie o wigilię firmową na 20 osób',
        ]);
        $wigilia->reminders()->create([
            'type' => 'reservation', 'title' => 'Potwierdzić liczbę gości na Wigilię',
            'remind_at' => now()->subHours(2),
        ]);
        $manager->tasks()->create([
            'contact_id' => $jan->id, 'title' => 'Potwierdzić menu na Wigilię B2B',
            'type' => 'offer', 'priority' => TaskPriority::High, 'due_date' => now()->addHours(3),
            'source_email_id' => 'demo-msg-jan-1', 'source_email_subject' => 'Zapytanie o wigilię firmową na 20 osób',
        ]);

        // --- Anna Nowak (VIP): birthday at table 5 ----------------------------------
        $anna = $waiter->contacts()->create([
            'email' => 'anna.nowak@example.com', 'name' => 'Anna Nowak', 'phone' => '+48 123 456 789',
            'status' => 'customer', 'is_client' => true, 'category_id' => $category('vip'),
        ]);
        $anna->customFields()->create(['label' => 'Alergie', 'value' => 'Orzechy, laktoza']);
        $anna->customFields()->create(['label' => 'Ulubiony stolik', 'value' => 'Stolik 5']);

        Activity::recordEmail($anna, [
            'message_id' => 'demo-msg-anna-1', 'subject' => 'Potwierdzenie rezerwacji - stolik nr 5.',
            'snippet' => 'Dziękuję, potwierdzam sobotę 19:00.', 'from' => 'anna.nowak@example.com',
            'direction' => 'out', 'sent_at' => $today->copy()->subDay()->setTime(14, 30)->utc(),
        ]);
        $birthday = $anna->reservations()->create([
            'reservation_date' => $today->copy()->addDays(3)->toDateString(), 'reservation_time' => '19:00',
            'guests_count' => 6, 'status' => 'confirmed', 'table_label' => 'Stolik 5', 'occasion' => 'Urodziny',
            'source_email_id' => 'demo-msg-anna-1',
        ]);
        $birthday->reminders()->create([
            'type' => 'reservation', 'title' => 'Potwierdzić tort na urodziny (Stolik 5)',
            'remind_at' => now()->addMinutes(90),
        ]);
        $birthday->reminders()->create([
            'type' => 'reservation', 'title' => 'Zamówić dekorację balonową',
            'remind_at' => now()->addDays(2),
        ]);
        $waiter->tasks()->create([
            'contact_id' => $anna->id, 'title' => 'Wysłać menu wegańskie w PDF', 'type' => 'follow_up',
            'due_date' => now()->addHours(2), 'source_email_id' => 'demo-msg-anna-1',
        ]);
        $waiter->tasks()->create([
            'contact_id' => $anna->id, 'title' => 'Zadzwonić ws. układu stołów', 'type' => 'follow_up',
            'due_date' => $today->copy()->addDay()->setTime(12, 0)->utc(),
        ]);

        // --- Marek Zając: plain contact (address book only) ------------------------
        $marek = $manager->contacts()->create(['email' => 'marek.zajac@example.com', 'name' => 'Marek Zając', 'status' => 'lead']);
        $manager->tasks()->create([
            'contact_id' => $marek->id, 'title' => 'Wysłać odpowiedź z cennikiem', 'type' => 'offer',
            'due_date' => $today->copy()->addDays(2)->setTime(10, 0)->utc(), 'source_email_subject' => 'Cennik sali',
        ]);

        // --- Internal task (no contact) ---------------------------------------------
        $manager->tasks()->create([
            'title' => 'Rozesłać grafik kelnerów', 'type' => 'internal',
            'due_date' => $today->copy()->addDays(5)->setTime(9, 0)->utc(), 'assigned_to' => $waiter->id,
        ]);
    }
}
