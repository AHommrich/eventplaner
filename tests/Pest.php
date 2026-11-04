<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Services\ManagementTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Global feature setup
|--------------------------------------------------------------------------
| DB safety guard runs in {@see Tests\TestCase::setUp()} and triggers on every
| unit/feature test — see there.
| Mail::fake prevents real Resend calls during email verification, etc.
| Storage::fake('s3') keeps the object-storage disk local — no cloud uploads from tests.
*/

pest()->beforeEach(function () {
    Mail::fake();
    Storage::fake('s3');
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
function actingAsGuest(Guest $guest): TestCase
{
    $token = $guest->createToken('test', ['role:guest'])->plainTextToken;

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

/** Issue a complete event-bound management device session for API feature tests. */
function managementTokenFor(User $user, ?Event $event = null): string
{
    $event ??= $user->accessibleEvents()->first();
    $event ??= Event::factory()->for($user, 'owner')->create();

    return app(ManagementTokenService::class)
        ->issue($user, $event, 'management-test')
        ->plainTextToken;
}

/** X-Event-ID header for management API feature tests. */
function managementHeaders(Event $event): array
{
    return ['X-Event-ID' => (string) $event->id];
}

/**
 * Authenticates an event owner and sets their event as the active event in the session.
 * Usage: $user = actingAsOwner(); $event = $user->ownedEvents->first();
 */
function actingAsOwner(?Event $event = null): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $event ??= Event::factory()->for($user, 'owner')->create();
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);

    return $user;
}

/**
 * Authenticates an admin user (role=admin).
 */
function actingAsAdmin(): User
{
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
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
