<?php

namespace Tests\Feature\Settings;

use App\Models\DevicePairing;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Note;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get(route('settings.export-data'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_download_their_data(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $event = Event::create([
            'user_id' => $user->id,
            'name' => 'My Wedding',
            'slug' => 'my-wedding',
            'date' => now()->addMonth(),
        ]);
        Guest::create([
            'event_id' => $event->id,
            'firstname' => 'Alice',
            'lastname' => 'Doe',
        ]);

        $response = $this->actingAs($user)->get(route('settings.export-data'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame(1, $payload['export_format_version']);
        $this->assertSame('owner@example.com', $payload['user']['email']);
        $this->assertCount(1, $payload['events']);
        $this->assertSame('My Wedding', $payload['events'][0]['name']);
        $this->assertSame('Alice', $payload['events'][0]['guests'][0]['firstname']);
    }

    public function test_export_does_not_leak_password_or_other_users_data(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);
        Event::create([
            'user_id' => $bob->id,
            'name' => 'Bob\'s Party',
            'slug' => 'bobs-party',
            'date' => now()->addMonth(),
        ]);

        $response = $this->actingAs($alice)->get(route('settings.export-data'));
        $body = $response->streamedContent();

        $this->assertStringNotContainsString('password', strtolower($body));
        $this->assertStringNotContainsString('remember_token', $body);
        $this->assertStringNotContainsString('bob@example.com', $body);
        $this->assertStringNotContainsString("Bob's Party", $body);
    }

    public function test_export_includes_notes_with_soft_deleted_marked(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'user_id' => $user->id,
            'name' => 'Noted Wedding',
            'slug' => 'noted-wedding',
            'date' => now()->addMonth(),
        ]);
        Note::factory()->create(['event_id' => $event->id, 'author_user_id' => $user->id, 'title' => 'Live note']);
        $trashed = Note::factory()->create(['event_id' => $event->id, 'author_user_id' => $user->id, 'title' => 'Trashed note']);
        $trashed->delete();

        $response = $this->actingAs($user)->get(route('settings.export-data'));
        $payload = json_decode($response->streamedContent(), true);

        $this->assertCount(2, $payload['notes']);
        $trashedExport = collect($payload['notes'])->firstWhere('title', 'Trashed note');
        $this->assertNotNull($trashedExport['deleted_at']);
        $this->assertSame('author', $trashedExport['relation']);
    }

    public function test_export_filename_carries_user_id_and_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.export-data'));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('eveplan-export-'.$user->id.'-', $disposition);
        $this->assertStringContainsString('.json', $disposition);
    }

    public function test_export_includes_device_pairing_metadata_without_secrets(): void
    {
        $user = User::factory()->create();
        DevicePairing::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'never-export-this'),
            'device_label' => 'My phone',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user)->get(route('settings.export-data'));
        $payload = json_decode($response->streamedContent(), true);
        $encoded = json_encode($payload);

        $this->assertSame('My phone', $payload['device_pairings'][0]['device_label']);
        $this->assertStringNotContainsString('never-export-this', $encoded);
        $this->assertStringNotContainsString(hash('sha256', 'never-export-this'), $encoded);
    }

    public function test_export_includes_push_token_metadata(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('export phone', ['management:*'])->accessToken;
        PushToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->id,
            'expo_token' => 'ExponentPushToken[export-device]',
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.export-data'));
        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('ExponentPushToken[export-device]', $payload['push_tokens'][0]['expo_token']);
        $this->assertSame('android', $payload['push_tokens'][0]['platform']);
    }
}
