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
| Global Feature-Setup
|--------------------------------------------------------------------------
| DB-Safety-Guard läuft in {@see Tests\TestCase::setUp()} und greift bei jedem
| Unit/Feature-Test — siehe dort.
| Mail::fake verhindert echte Resend-Calls bei Email-Verification etc.
| Storage::fake('r2') hält den R2/S3-Disk lokal — kein Cloud-Upload aus Tests.
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
 * Authentifiziert einen Gast via Sanctum-Bearer-Token und setzt den Header.
 * Verwendung: actingAsGuest($guest)->get('/api/event/info')
 */
function actingAsGuest(\App\Models\Guest $guest): \Tests\TestCase
{
    $token = $guest->createToken('test')->plainTextToken;
    return test()->withHeader('Authorization', 'Bearer ' . $token);
}

/**
 * Authentifiziert einen Event-Owner und setzt sein Event als aktives Event in der Session.
 * Verwendung: $user = actingAsOwner(); $event = $user->ownedEvents->first();
 */
function actingAsOwner(?\App\Models\Event $event = null): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $event ??= \App\Models\Event::factory()->for($user, 'owner')->create();
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);
    return $user;
}

/**
 * Authentifiziert einen Admin-User (role=admin).
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
