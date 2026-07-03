<?php

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\Photo;
use App\Services\GuestDataExporter;

it('returns the caller data with format version and self block', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create([
        'event_id' => $event->id,
        'firstname' => 'Anna',
        'lastname' => 'Muster',
    ]);

    actingAsGuest($guest)->getJson('/api/guest/export')
        ->assertOk()
        ->assertJsonPath('format_version', GuestDataExporter::FORMAT_VERSION)
        ->assertJsonPath('guest.id', $guest->id)
        ->assertJsonPath('guest.firstname', 'Anna')
        ->assertJsonPath('guest.lastname', 'Muster')
        ->assertJsonStructure([
            'format_version',
            'generated_at',
            'guest' => ['id', 'firstname', 'lastname', 'rsvp_status'],
            'family_members',
            'photos',
            'drink_logs',
            'photo_game_submission',
        ]);
});

it('lists family members with name and rsvp only, no sensitive fields', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id, 'name' => 'Familie X']);
    $me = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $sibling = Guest::factory()->create([
        'event_id' => $event->id,
        'group_id' => $group->id,
        'firstname' => 'Ben',
        'rsvp_status' => 'accepted',
    ]);

    $response = actingAsGuest($me)->getJson('/api/guest/export')->assertOk();

    $members = $response->json('family_members');
    expect($members)->toHaveCount(1);
    expect($members[0])->toEqualCanonicalizing([
        'id' => $sibling->id,
        'firstname' => 'Ben',
        'lastname' => $sibling->lastname,
        'rsvp_status' => 'accepted',
    ]);
});

it('only exposes photos owned by the caller', function () {
    $event = Event::factory()->create();
    $me = Guest::factory()->create(['event_id' => $event->id]);
    $other = Guest::factory()->create(['event_id' => $event->id]);

    Photo::create([
        'event_id' => $event->id,
        'guest_id' => $me->id,
        'uploaded_by' => $me->firstname,
        'url' => 'https://cdn.example/mine.jpg',
        'r2_key' => 'events/1/mine.jpg',
    ]);
    Photo::create([
        'event_id' => $event->id,
        'guest_id' => $other->id,
        'uploaded_by' => $other->firstname,
        'url' => 'https://cdn.example/other.jpg',
        'r2_key' => 'events/1/other.jpg',
    ]);

    $response = actingAsGuest($me)->getJson('/api/guest/export')->assertOk();
    expect($response->json('photos'))->toHaveCount(1);
    expect($response->json('photos.0.url'))->toBe('https://cdn.example/mine.jpg');
});

it('requires authentication', function () {
    $this->getJson('/api/guest/export')->assertStatus(401);
});
