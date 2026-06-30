<?php

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\PhotoAlbum;
use App\Models\User;

it('lets an admin create a new event with default albums', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $this->actingAs($admin);

    $this->post(route('events.store'), [
        'name' => 'Sommerfest 2027',
        'date' => '2027-07-15',
    ])->assertRedirect(route('dashboard'));

    $event = Event::where('name', 'Sommerfest 2027')->first();
    expect($event)->not->toBeNull();
    expect($event->projector_token)->toHaveLength(32);

    // 3 default albums created
    expect(PhotoAlbum::where('event_id', $event->id)->count())->toBe(3);
});

it('rejects event creation for non-admin users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->post(route('events.store'), ['name' => 'X'])->assertStatus(403);
});

it('creates an event-join request for regular users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $this->post(route('events.request'), ['event_name' => 'Geburtstag von Lisa'])
        ->assertRedirect(route('no-event'));

    expect(EventRequest::where('user_id', $user->id)->where('event_name', 'Geburtstag von Lisa')->exists())->toBeTrue();
});

it('switches the active event in session', function () {
    $user = actingAsOwner();
    $other = Event::factory()->for($user, 'owner')->create();

    // non-Inertia request → normal 302 redirect, session value is still updated
    $this->post(route('events.switch'), ['event_id' => $other->id]);

    expect(session('active_event_id'))->toBe($other->id);
});
