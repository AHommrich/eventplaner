<?php

use App\Models\Event;
use App\Models\User;

it('allows user with event access to has_event routes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $event = Event::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->withSession(['active_event_id' => $event->id])
        ->get('/dashboard')
        ->assertOk();
});

it('blocks user without any event from has_event routes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(); // wird auf /onboarding oder /no-event umgeleitet
});

it('allows admin user to access /admin/users', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

it('blocks non-admin user from /admin/users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]); // default role = 'user'

    // EnsureUserIsAdmin leitet auf /onboarding um statt 403 abzuwerfen
    $this->actingAs($user)
        ->get('/admin/users')
        ->assertRedirect(route('onboarding'));
});
