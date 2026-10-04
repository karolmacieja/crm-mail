<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $anna;

    private User $bartek;

    private ContactCategory $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create();
        $this->anna = User::factory()->for($this->group)->licensed()->create(['name' => 'Anna']);
        $this->bartek = User::factory()->for($this->group)->licensed()->create(['name' => 'Bartek']);

        $this->actingAsExtension($this->anna);
        $this->business = ContactCategory::query()->forGroup($this->group)->find(
            $this->postJson('/api/settings/categories', ['name' => 'Korespondencja firmowa'])->assertCreated()->json('data.id')
        );
        $this->patchJson("/api/settings/categories/{$this->business->id}", ['is_private' => true])
            ->assertOk()->assertJsonPath('data.is_private', true);
    }

    private function as(User $user): static
    {
        return $this->actingAsExtension($user);
    }

    private function card(User $user, array $data = []): int
    {
        $this->as($user);

        return $this->postJson('/api/contacts', $data + ['email' => 'biuro@hurtownia.pl', 'name' => 'Hurtownia', 'category_id' => $this->business->id])
            ->assertCreated()
            ->assertJsonPath('data.is_personal', true)
            ->json('data.id');
    }

    public function test_every_person_keeps_a_private_card_of_the_same_contact(): void
    {
        $annas = $this->card($this->anna, ['phone' => '+48 111 111 111']);
        $this->postJson('/api/contacts', ['email' => 'biuro@hurtownia.pl', 'category_id' => $this->business->id])
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('crm.personal.already_have_card'));

        // Bartek does not see Anna's card, but learns that colleagues keep this contact privately.
        $this->as($this->bartek);
        $this->getJson("/api/contacts/{$annas}")->assertNotFound();
        $this->getJson('/api/contacts')->assertJsonCount(0, 'data');
        $this->getJson('/api/contacts/lookup?email=biuro@hurtownia.pl')
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.personal_cards.category.name', 'Korespondencja firmowa');

        // Creating it without a category still gives him his own personal card.
        $bartkas = $this->postJson('/api/contacts', ['email' => 'biuro@hurtownia.pl', 'name' => 'Hurtownia (moja)'])
            ->assertCreated()
            ->assertJsonPath('data.is_personal', true)
            ->assertJsonPath('data.category_id', $this->business->id)
            ->json('data.id');
        $this->assertNotEquals($annas, $bartkas);
        $this->getJson('/api/contacts/lookup?email=biuro@hurtownia.pl')->assertJsonPath('data.id', $bartkas);

        $this->as($this->anna);
        $this->getJson('/api/contacts/lookup?email=biuro@hurtownia.pl')
            ->assertJsonPath('data.id', $annas)
            ->assertJsonPath('data.phone', '+48 111 111 111');
        $this->getJson('/api/contacts')->assertJsonCount(1, 'data');
    }

    public function test_personal_cards_cannot_be_shared(): void
    {
        $annas = $this->card($this->anna);

        $this->postJson("/api/contacts/{$annas}/shares", ['scopes' => ['details']])->assertStatus(422);
        $this->postJson("/api/contacts/{$annas}/transfer", ['user_id' => $this->bartek->id])->assertStatus(422);
    }

    public function test_selected_emails_go_to_the_team_pool(): void
    {
        $annas = $this->card($this->anna);
        $offer = Activity::recordEmail(Contact::find($annas), ['message_id' => 'm-offer', 'subject' => 'Cennik 2027'], $this->anna);
        $private = Activity::recordEmail(Contact::find($annas), ['message_id' => 'm-private', 'subject' => 'Prywatne ustalenia'], $this->anna);
        $bartkas = $this->card($this->bartek);
        // Both received the same circular – it must not appear twice on Bartek's card.
        Activity::recordEmail(Contact::find($bartkas), ['message_id' => 'm-circular', 'subject' => 'Okólnik'], $this->bartek);
        $circular = Activity::recordEmail(Contact::find($annas), ['message_id' => 'm-circular', 'subject' => 'Okólnik'], $this->anna);

        $this->as($this->anna);
        $this->postJson("/api/contacts/{$annas}/activities/{$offer->id}/team-share")
            ->assertOk()->assertJsonPath('data.team_shared', true)->assertJsonPath('data.team_shared_by.name', 'Anna');
        $this->postJson("/api/contacts/{$annas}/activities/{$circular->id}/team-share")->assertOk();

        // Bartek cannot touch Anna's emails.
        $this->as($this->bartek);
        $this->postJson("/api/contacts/{$annas}/activities/{$private->id}/team-share")->assertNotFound();
        $this->postJson("/api/contacts/{$bartkas}/activities/{$private->id}/team-share")->assertNotFound();

        $titles = collect($this->getJson("/api/contacts/{$bartkas}/activities?type=email")->assertOk()->json('data'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Okólnik', 'Cennik 2027'], $titles->all());

        // Somebody without a card sees how many emails the pool holds.
        $third = User::factory()->for($this->group)->licensed()->create();
        $this->as($third);
        $this->getJson('/api/contacts/lookup?email=biuro@hurtownia.pl')->assertJsonPath('meta.personal_cards.team_emails', 2);

        // Taking it back hides it again.
        $this->as($this->anna);
        $this->deleteJson("/api/contacts/{$annas}/activities/{$offer->id}/team-share")->assertOk()->assertJsonPath('data.team_shared', false);
        $this->as($this->bartek);
        $titles = collect($this->getJson("/api/contacts/{$bartkas}/activities?type=email")->json('data'))->pluck('title');
        $this->assertEquals(['Okólnik'], $titles->all());
    }

    public function test_moving_a_shared_contact_into_the_private_category_makes_it_personal(): void
    {
        $this->as($this->anna);
        $id = $this->postJson('/api/contacts', ['email' => 'dostawca@ryby.pl'])->json('data.id');
        $this->postJson("/api/contacts/{$id}/shares", ['scopes' => ['details']])->assertCreated();

        $this->patchJson("/api/contacts/{$id}", ['category_id' => $this->business->id])
            ->assertOk()->assertJsonPath('data.is_personal', true);
        $this->assertDatabaseCount('contact_shares', 0);

        $this->as($this->bartek);
        $this->getJson("/api/contacts/{$id}")->assertNotFound();
        $bartkas = $this->postJson('/api/contacts', ['email' => 'dostawca@ryby.pl'])->assertCreated()->json('data.id');

        // Back to a shared category is blocked while two people keep cards.
        $this->as($this->anna);
        $this->patchJson("/api/contacts/{$id}", ['category_id' => null])->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->patchJson("/api/settings/categories/{$this->business->id}", ['is_private' => false])
            ->assertStatus(422)->assertJsonValidationErrors('is_private');
        $this->deleteJson("/api/settings/categories/{$this->business->id}")->assertStatus(422);

        $this->as($this->bartek);
        $this->deleteJson("/api/contacts/{$bartkas}")->assertNoContent();
        $this->as($this->anna);
        $this->patchJson("/api/contacts/{$id}", ['category_id' => null])->assertOk()->assertJsonPath('data.is_personal', false);
    }

    public function test_a_shared_card_and_personal_cards_of_one_email_never_mix(): void
    {
        $this->as($this->anna);
        $this->postJson('/api/contacts', ['email' => 'wspolny@firma.pl'])->assertCreated();

        $this->as($this->bartek);
        $this->postJson('/api/contacts', ['email' => 'wspolny@firma.pl', 'category_id' => $this->business->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', __('crm.sharing.email_owned_by', ['name' => 'Anna']));
    }

    public function test_turning_privacy_on_converts_the_categorys_contacts(): void
    {
        $this->as($this->anna);
        $vip = $this->postJson('/api/settings/categories', ['name' => 'Firmy'])->json('data.id');
        $id = $this->postJson('/api/contacts', ['email' => 'kontakt@firma.pl', 'category_id' => $vip])->json('data.id');
        $this->postJson("/api/contacts/{$id}/shares", ['scopes' => ['details']])->assertCreated();

        $this->patchJson("/api/settings/categories/{$vip}", ['is_private' => true])->assertOk();

        $this->getJson("/api/contacts/{$id}")->assertJsonPath('data.is_personal', true);
        $this->as($this->bartek);
        $this->getJson("/api/contacts/{$id}")->assertNotFound();
    }
}
