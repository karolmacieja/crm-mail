<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->licensed()->create();
        $this->actingAsExtension($this->user);
    }

    public function test_settings_lists_the_groups_dictionaries_with_usage(): void
    {
        Contact::factory()->for($this->user->group)->create(['status' => 'customer']);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonCount(3, 'data.categories')
            ->assertJsonCount(4, 'data.statuses')
            ->assertJsonCount(3, 'data.task_categories')
            ->assertJsonCount(2, 'data.field_templates')
            ->assertJsonPath('data.statuses.0.is_default', true)
            ->assertJsonPath('data.statuses.2.usage_count', 1);
    }

    public function test_custom_status_and_task_category_are_usable_right_away(): void
    {
        $this->postJson('/api/settings/statuses', ['name' => 'Stały gość', 'color' => 'purple'])
            ->assertCreated()->assertJsonPath('data.key', 'staly_gosc');
        $this->postJson('/api/settings/task-categories', ['name' => 'Eventy', 'icon' => 'cake-candles'])
            ->assertCreated()->assertJsonPath('data.key', 'eventy');

        $this->postJson('/api/contacts', ['email' => 'a@b.pl', 'status' => 'staly_gosc'])->assertCreated()->assertJsonPath('data.status', 'staly_gosc');
        $this->postJson('/api/contacts', ['email' => 'c@d.pl', 'status' => 'vip_unknown'])->assertJsonValidationErrors('status');
        $this->postJson('/api/tasks', ['title' => 'Tort', 'type' => 'eventy'])->assertCreated()->assertJsonPath('data.type', 'eventy');
    }

    public function test_default_status_is_unique_and_used_for_new_contacts(): void
    {
        $vip = $this->postJson('/api/settings/statuses', ['name' => 'Nowy', 'is_default' => true])->json('data');

        $this->assertSame(1, ContactStatus::where('is_default', true)->count());
        $this->postJson('/api/contacts', ['email' => 'nowy@gosc.pl'])->assertJsonPath('data.status', $vip['key']);
    }

    public function test_deleting_a_status_in_use_requires_a_target(): void
    {
        $contact = Contact::factory()->for($this->user->group)->create(['status' => 'prospect']);
        $prospect = ContactStatus::where('key', 'prospect')->first();

        $this->deleteJson("/api/settings/statuses/{$prospect->id}")->assertStatus(422)->assertJsonValidationErrors('move_to');
        $this->deleteJson("/api/settings/statuses/{$prospect->id}?move_to=customer")->assertNoContent();

        $this->assertSame('customer', $contact->fresh()->status);
    }

    public function test_deleting_the_default_status_promotes_another_one_and_the_last_cannot_go(): void
    {
        $lead = ContactStatus::where('key', 'lead')->first();
        $this->deleteJson("/api/settings/statuses/{$lead->id}")->assertNoContent();
        $this->assertSame(1, ContactStatus::where('is_default', true)->count());

        ContactStatus::where('key', '!=', 'inactive')->get()->each(fn ($s) => $this->deleteJson("/api/settings/statuses/{$s->id}")->assertNoContent());
        $last = ContactStatus::firstOrFail();
        $this->deleteJson("/api/settings/statuses/{$last->id}")->assertStatus(422);
    }

    public function test_task_category_reassignment_and_reorder(): void
    {
        $task = Task::factory()->for(Contact::factory()->for($this->user->group))->create(['type' => 'offer']);
        $offer = TaskCategory::where('key', 'offer')->first();

        $this->deleteJson("/api/settings/task-categories/{$offer->id}?move_to=follow_up")->assertNoContent();
        $this->assertSame('follow_up', $task->fresh()->type);

        $ids = TaskCategory::query()->ordered()->pluck('id')->reverse()->values()->all();
        $this->postJson('/api/settings/task-categories/reorder', ['ids' => $ids])
            ->assertOk()->assertJsonPath('data.0.id', $ids[0]);
    }

    public function test_contact_categories_and_field_templates_crud(): void
    {
        $id = $this->postJson('/api/settings/categories', ['name' => 'Firmy eventowe', 'color' => 'orange', 'icon' => 'cake-candles'])
            ->assertCreated()->assertJsonPath('data.slug', 'firmy-eventowe')->json('data.id');
        $this->postJson('/api/settings/categories', ['name' => 'Firmy eventowe'])->assertJsonValidationErrors('name');
        $this->patchJson("/api/settings/categories/{$id}", ['color' => 'pink'])->assertOk()->assertJsonPath('data.color', 'pink');
        $this->patchJson("/api/settings/categories/{$id}", ['color' => 'neon'])->assertJsonValidationErrors('color');

        $contact = Contact::factory()->for($this->user->group)->create(['category_id' => $id]);
        $this->deleteJson("/api/settings/categories/{$id}")->assertNoContent();
        $this->assertNull($contact->fresh()->category_id);

        $this->postJson('/api/settings/field-templates', ['label' => 'Ulubione wino', 'type' => 'text'])->assertCreated();
        $this->getJson('/api/settings')->assertJsonCount(3, 'data.field_templates');
    }

    public function test_dictionaries_are_isolated_per_restaurant(): void
    {
        $other = User::factory()->licensed()->create();
        $foreign = ContactStatus::query()->forGroup($other->group)->first();

        $this->patchJson("/api/settings/statuses/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/settings/statuses/{$foreign->id}")->assertNotFound();
        // Same name is fine in another restaurant.
        $this->postJson('/api/settings/statuses', ['name' => 'Lead'])->assertJsonValidationErrors('name');
        $this->actingAsExtension($other);
        $this->postJson('/api/settings/statuses', ['name' => 'Stały gość'])->assertCreated();
    }
}
