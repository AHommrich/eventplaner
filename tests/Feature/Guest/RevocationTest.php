<?php

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Guest;
use App\Models\PhotoAlbum;
use App\Models\User;

it('approves a guest revocation request', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'revocation_requested']);

    $this->post(route('requests.revocations.approve', $guest))->assertRedirect();

    expect($guest->fresh()->rsvp_status)->toBeNull();
});

it('declines a guest revocation request (stays declined)', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'revocation_requested']);

    $this->post(route('requests.revocations.decline', $guest))->assertRedirect();

    expect($guest->fresh()->rsvp_status)->toBe('declined');
});

it('approves an event-join request and creates the event with default albums', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $applicant = User::factory()->create();
    $request   = EventRequest::create(['user_id' => $applicant->id, 'event_name' => 'Sommerfest', 'status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('requests.event-requests.approve', $request))
        ->assertRedirect();

    $event = Event::where('user_id', $applicant->id)->where('name', 'Sommerfest')->first();
    expect($event)->not->toBeNull();
    expect(PhotoAlbum::where('event_id', $event->id)->count())->toBe(3);
    expect(EventRequest::find($request->id))->toBeNull();
});

it('declines an event-join request', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $applicant = User::factory()->create();
    $request   = EventRequest::create(['user_id' => $applicant->id, 'event_name' => 'X', 'status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('requests.event-requests.decline', $request))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('declined');
});
