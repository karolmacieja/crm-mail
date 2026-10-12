<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use App\Support\Export\DataExporter;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $anna;

    private Contact $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create(['name' => 'Pod Lipą', 'slug' => 'pod-lipa']);
        $this->anna = User::factory()->for($this->group)->licensed()->create(['name' => 'Anna', 'email' => 'anna@pod-lipa.pl']);
        app(Tenancy::class)->runAs($this->group, function () {
            $this->client = Contact::factory()->for($this->group)->create(['email' => 'gosc@firma.pl', 'user_id' => $this->anna->id, 'phone' => '+48 600 000 000']);
            $this->client->customFields()->create(['label' => 'Alergie', 'key' => 'alergie', 'value' => 'orzechy']);
            Activity::recordEmail($this->client, ['message_id' => 'm-1', 'subject' => 'Wesele 12.06', 'snippet' => 'Dzień dobry', 'from' => 'gosc@firma.pl'], $this->anna);
            $this->client->addNote('Lubi stolik przy oknie', $this->anna);
            Task::factory()->for($this->client)->create(['user_id' => $this->anna->id, 'title' => 'Wysłać menu']);
        });

        // Another restaurant's data must never leak into the export.
        Contact::factory()->create(['email' => 'obcy@firma.pl']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/exports'));
        parent::tearDown();
    }

    public function test_restaurant_export_contains_everything_but_secrets(): void
    {
        $data = app(DataExporter::class)->restaurant($this->group);

        $this->assertSame('Pod Lipą', $data['restaurant']['name']);
        $this->assertCount(1, $data['contacts']);
        $contact = $data['contacts'][0];
        $this->assertSame('gosc@firma.pl', $contact['email']);
        $this->assertSame('anna@pod-lipa.pl', $contact['owner']['email']);
        $this->assertSame('orzechy', $contact['custom_fields'][0]['value']);
        $this->assertSame('Wysłać menu', $contact['tasks'][0]['title']);
        $email = collect($contact['timeline'])->firstWhere('type', 'email');
        $this->assertSame('Wesele 12.06', $email['subject']);
        $this->assertSame('m-1', $email['gmail_message_id']);

        $json = json_encode($data);
        $this->assertStringNotContainsString('obcy@firma.pl', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString($this->anna->password, $json);
    }

    public function test_command_writes_json_and_csv(): void
    {
        $this->artisan('crm:export', ['--group' => 'pod-lipa'])->assertSuccessful();

        $dir = collect(File::directories(storage_path('app/exports')))->first();
        $this->assertFileExists("{$dir}/export.json");
        $csv = File::get("{$dir}/kontakty.csv");
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('gosc@firma.pl;', $csv);
        $this->assertStringContainsString('Alergie: orzechy', $csv);
    }

    public function test_single_email_and_user_exports(): void
    {
        $this->artisan('crm:export', ['--group' => 'Pod Lipą', '--email' => 'GOSC@firma.pl'])->assertSuccessful();
        $file = collect(File::directories(storage_path('app/exports')))->first().'/export.json';
        $data = json_decode(File::get($file), true);
        $this->assertSame('gosc@firma.pl', $data['contacts'][0]['email']);

        $user = app(DataExporter::class)->user($this->anna);
        $this->assertSame('anna@pod-lipa.pl', $user['account']['email']);
        $this->assertSame('Lubi stolik przy oknie', $user['notes_written'][0]['body']);
        $this->assertSame('gosc@firma.pl', $user['owned_contacts'][0]['email']);

        $this->artisan('crm:export', ['--user' => 'anna@pod-lipa.pl'])->assertSuccessful();
        $this->artisan('crm:export', ['--group' => 'nie-ma'])->assertFailed();
    }
}
