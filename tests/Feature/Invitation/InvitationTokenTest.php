<?php

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;

it('shows the invitations index page', function () {
    actingAsOwner();

    $this->get(route('invitations'))->assertOk();
});

it('generates tokens for all groups and solo guests in the active event', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $solo  = Guest::factory()->create(['event_id' => $event->id, 'group_id' => null]);

    $this->post(route('invitations.generate'))->assertRedirect();

    expect(InvitationToken::where('group_id', $group->id)->exists())->toBeTrue();
    expect(InvitationToken::where('guest_id', $solo->id)->exists())->toBeTrue();
});

it('regenerates a token for a specific group', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $group = Group::factory()->create(['event_id' => $event->id]);

    $this->post(route('invitations.generate.group', $group))->assertRedirect();

    $token = InvitationToken::where('group_id', $group->id)->first();
    expect($token)->not->toBeNull();
    expect(strlen($token->token))->toBe(32);
});

it('regenerates a token for a solo guest', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $this->post(route('invitations.generate.guest', $guest))->assertRedirect();

    expect(InvitationToken::where('guest_id', $guest->id)->exists())->toBeTrue();
});

it('rejects token generation for a guest from another event', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreign    = Guest::factory()->create(['event_id' => $otherEvent->id]);

    $this->post(route('invitations.generate.guest', $foreign))->assertStatus(403);
});
