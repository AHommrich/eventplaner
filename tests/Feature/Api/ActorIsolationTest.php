<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;

it('rejects a User management token on guest routes', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $token = $user->createToken('management-app', ['management:*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/event/info')
        ->assertForbidden();
});

it('rejects a Guest token on management routes', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $guest = Guest::factory()->for($event)->create(['app_access' => true]);
    $token = $guest->createToken('guest-login', ['role:guest'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/management/me')
        ->assertForbidden();
});

it('rejects a User token without the management ability', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $token = $user->createToken('wrong-surface', ['role:guest'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/management/me')
        ->assertForbidden();
});

it('allows only an approved verified User with the management ability', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $token = $user->createToken('management-app', ['management:*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/management/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('rechecks verification and approval for every management request', function (array $attributes) {
    $user = User::factory()->create(array_merge(['is_approved' => true], $attributes));
    $token = $user->createToken('management-app', ['management:*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/management/me')
        ->assertForbidden();
})->with([
    'unverified' => [['email_verified_at' => null]],
    'unapproved' => [['is_approved' => false]],
]);
