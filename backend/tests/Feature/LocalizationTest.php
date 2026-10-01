<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_follow_accept_language(): void
    {
        $user = User::factory()->licensed()->create();
        Contact::factory()->for($user->group)->create(['email' => 'dup@acme.com']);
        Sanctum::actingAs($user);

        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9')
            ->postJson('/api/contacts', ['email' => 'dup@acme.com', 'phone' => 'zadzwoń'])
            ->assertStatus(422)
            ->assertHeader('Content-Language', 'pl')
            ->assertJsonPath('errors.email.0', 'Kontakt z tym adresem e-mail już istnieje w Twoim CRM.')
            ->assertJsonPath('errors.phone.0', 'Numer telefonu może zawierać tylko cyfry, spacje oraz znaki + ( ) . - / x.');

        $this->withHeader('Accept-Language', 'pl')
            ->postJson('/api/tasks', [])
            ->assertJsonPath('errors.title.0', 'Pole tytuł jest wymagane.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/tasks', [])
            ->assertJsonPath('errors.title.0', 'The title field is required.');
    }

    public function test_failed_login_message_is_localized(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->withHeader('Accept-Language', 'pl')
            ->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Nieprawidłowy e-mail lub hasło.');
    }

    public function test_unsupported_language_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'de-DE')
            ->postJson('/api/auth/login', [])
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }
}
