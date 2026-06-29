<?php

use App\Models\Event;
use App\Models\Guest;

it('returns 401 without a bearer token on /api/event/info', function () {
    $this->getJson('/api/event/info')->assertStatus(401);
});

it('allows guest with app_access to /api/event/info', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'app_access' => true]);

    actingAsGuest($guest)
        ->getJson('/api/event/info')
        ->assertOk();
});

it('rejects guest without app_access from /api/event/info', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->withoutAppAccess()->create(['event_id' => $event->id]);

    actingAsGuest($guest)
        ->getJson('/api/event/info')
        ->assertStatus(403)
        ->assertJson(['code' => 'app_blocked']);
});

it('rejects guest without drinks_access from /api/drinks', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->withoutDrinksAccess()->create(['event_id' => $event->id]);

    actingAsGuest($guest)
        ->getJson('/api/drinks')
        ->assertStatus(403)
        ->assertJson(['code' => 'drinks_blocked']);
});

it('allows guest with drinks_access to /api/drinks', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'drinks_access' => true]);

    actingAsGuest($guest)
        ->getJson('/api/drinks')
        ->assertOk();
});
