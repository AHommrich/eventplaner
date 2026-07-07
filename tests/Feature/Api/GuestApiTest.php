<?php

use App\Mail\RevocationRequestMail;
use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use Illuminate\Support\Facades\Mail;

it('returns the current guest profile via /me', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'firstname' => 'Anna']);

    actingAsGuest($guest)->getJson('/api/guest/me')
        ->assertOk()
        ->assertJson([
            'guest_id' => $guest->id,
            'firstname' => 'Anna',
            'type' => 'solo',
        ]);
});

it('lists family members in /me for grouped guests', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id, 'name' => 'Familie Test']);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $partner = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);

    actingAsGuest($guest)->getJson('/api/guest/me')
        ->assertOk()
        ->assertJsonPath('type', 'family')
        ->assertJsonPath('family_name', 'Familie Test')
        ->assertJsonCount(1, 'group_members')
        ->assertJsonPath('group_members.0.guest_id', $partner->id);
});

it('accepts a yes-rsvp for the current guest as pending', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->postJson('/api/guest/rsvp', ['attending' => true])
        ->assertOk()
        ->assertJson(['rsvp_status' => 'accepted_pending']);

    expect($guest->fresh()->rsvp_status)->toBe('accepted_pending');
});

it('accepts a no-rsvp for the current guest as pending', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->postJson('/api/guest/rsvp', ['attending' => false])
        ->assertOk()
        ->assertJson(['rsvp_status' => 'declined_pending']);
});

it('blocks rsvp change once status is final', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'accepted']);

    actingAsGuest($guest)->postJson('/api/guest/rsvp', ['attending' => false])
        ->assertStatus(422);
});

it('accepts a rsvp for a family member when actor has confirmed', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $actor = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id, 'rsvp_status' => 'accepted_pending']);
    $target = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);

    actingAsGuest($actor)->postJson("/api/guest/{$target->id}/rsvp", ['attending' => true])
        ->assertOk()
        ->assertJsonPath('rsvp_status', 'accepted_pending');
});

it('rejects rsvp for a guest from another family group', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $otherGroup = Group::factory()->create(['event_id' => $event->id]);
    $actor = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id, 'rsvp_status' => 'accepted_pending']);
    $foreign = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $otherGroup->id]);

    actingAsGuest($actor)->postJson("/api/guest/{$foreign->id}/rsvp", ['attending' => true])
        ->assertStatus(403);
});

it('creates a revocation request when /revoke is called on declined', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'declined']);

    actingAsGuest($guest)->postJson('/api/guest/rsvp/revoke')
        ->assertOk()
        ->assertJsonPath('rsvp_status', 'revocation_requested');

    expect($guest->fresh()->rsvp_status)->toBe('revocation_requested');

    Mail::assertQueued(RevocationRequestMail::class);
});

it('rejects revoke when guest has no rsvp yet', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => null]);

    actingAsGuest($guest)->postJson('/api/guest/rsvp/revoke')
        ->assertStatus(422);
});
