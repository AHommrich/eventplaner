<?php

use App\Models\Event;
use App\Models\User;

it('lists owned and shared events with the effective role without an event header', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $owned = Event::factory()->for($user, 'owner')->create(['name' => 'Owned']);
    $shared = Event::factory()->create(['name' => 'Shared']);
    $foreign = Event::factory()->create(['name' => 'Foreign']);
    $shared->users()->attach($user, ['role' => 'event_admin']);

    $this->withToken(managementTokenFor($user))
        ->getJson('/api/management/me/events')
        ->assertOk()
        ->assertJsonFragment(['id' => $owned->id, 'name' => 'Owned', 'my_role' => 'owner'])
        ->assertJsonFragment(['id' => $shared->id, 'name' => 'Shared', 'my_role' => 'event_admin'])
        ->assertJsonMissing(['id' => $foreign->id]);
});

it('lists every event as superadmin with the superadmin role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $first = Event::factory()->create();
    $second = Event::factory()->create();

    $this->withToken(managementTokenFor($admin))
        ->getJson('/api/management/me/events')
        ->assertOk()
        ->assertJsonFragment(['id' => $first->id, 'my_role' => 'superadmin'])
        ->assertJsonFragment(['id' => $second->id, 'my_role' => 'superadmin']);
});

it('requires management authentication but not X-Event-ID', function () {
    $this->getJson('/api/management/me/events')->assertUnauthorized();
});
