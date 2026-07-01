<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Global feature setup
|--------------------------------------------------------------------------
| DB safety guard runs in {@see Tests\TestCase::setUp()} and triggers on every
| unit/feature test — see there.
| Mail::fake prevents real Resend calls during email verification, etc.
| Storage::fake('r2') keeps the R2/S3 disk local — no cloud uploads from tests.
*/

pest()->beforeEach(function () {
    Mail::fake();
    Storage::fake('r2');
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Authenticates a guest via Sanctum bearer token and sets the header.
 * Usage: actingAsGuest($guest)->get('/api/event/info')
 */
function actingAsGuest(\App\Models\Guest $guest): \Tests\TestCase
{
    $token = $guest->createToken('test')->plainTextToken;

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

/**
 * Authenticates an event owner and sets their event as the active event in the session.
 * Usage: $user = actingAsOwner(); $event = $user->ownedEvents->first();
 */
function actingAsOwner(?\App\Models\Event $event = null): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $event ??= \App\Models\Event::factory()->for($user, 'owner')->create();
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);

    return $user;
}

/**
 * Authenticates an admin user (role=admin).
 */
function actingAsAdmin(): \App\Models\User
{
    $admin = \App\Models\User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    test()->actingAs($admin);

    return $admin;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
