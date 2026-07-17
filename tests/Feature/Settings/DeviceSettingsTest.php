<?php

use App\Models\DevicePairing;
use App\Models\User;

it('renders only the authenticated users paired devices', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $other = User::factory()->create();
    $token = $user->createToken('Phone', ['management:*'])->accessToken;
    $pairing = DevicePairing::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', str_repeat('a', 64)),
        'device_label' => 'My phone',
        'expires_at' => now(),
        'redeemed_at' => now(),
        'personal_access_token_id' => $token->id,
    ]);
    DevicePairing::create([
        'user_id' => $other->id,
        'token_hash' => hash('sha256', str_repeat('b', 64)),
        'device_label' => 'Foreign phone',
        'expires_at' => now(),
        'redeemed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.devices'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Devices')
            ->has('devices', 1)
            ->where('devices.0.id', $pairing->id)
            ->where('devices.0.device_label', 'My phone'));
});

it('creates a pairing secret from the authenticated web settings session', function () {
    $user = User::factory()->create(['is_approved' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('settings.devices.pairings'), [
            'device_label' => 'New phone',
        ])
        ->assertCreated();

    $plainToken = $response->json('pairing_token');
    expect(DevicePairing::first()->token_hash)->toBe(hash('sha256', $plainToken));
});

it('forbids revoking another users paired device from web settings', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $other = User::factory()->create();
    $pairing = DevicePairing::create([
        'user_id' => $other->id,
        'token_hash' => hash('sha256', str_repeat('c', 64)),
        'expires_at' => now(),
    ]);

    $this->actingAs($user)
        ->deleteJson(route('settings.devices.destroy', $pairing))
        ->assertForbidden();
});
