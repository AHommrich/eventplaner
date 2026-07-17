<?php

use App\Models\DevicePairing;
use App\Models\PushToken;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('stores only a hash for a short-lived pairing secret', function () {
    $user = User::factory()->create(['is_approved' => true]);

    $response = $this->actingAs($user)
        ->postJson(route('settings.devices.pairings'), [
            'device_label' => 'New phone',
        ])
        ->assertCreated();

    $plainToken = $response->json('pairing_token');
    $pairing = DevicePairing::firstOrFail();
    expect($pairing->token_hash)->not->toBe($plainToken)
        ->and($pairing->token_hash)->toBe(hash('sha256', $plainToken))
        ->and($pairing->expires_at->between(now()->addMinutes(9), now()->addMinutes(11)))->toBeTrue();
});

it('atomically redeems a pairing once and records the minted device token', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $plainToken = $this->actingAs($user)
        ->postJson(route('settings.devices.pairings'))
        ->json('pairing_token');

    $response = $this->postJson('/api/auth/pair', [
        'token' => $plainToken,
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonStructure(['token', 'user']);

    $pairing = DevicePairing::firstOrFail();
    $deviceToken = PersonalAccessToken::findOrFail($pairing->personal_access_token_id);
    expect($pairing->redeemed_at)->not->toBeNull()
        ->and($pairing->token_hash)->toBeNull()
        ->and($pairing->device_label)->toBe('iPhone')
        ->and($deviceToken->expires_at)->not->toBeNull()
        ->and($deviceToken->can('management:*'))->toBeTrue();

    $this->postJson('/api/auth/pair', [
        'token' => $plainToken,
        'device_name' => 'Second scan',
    ])->assertUnprocessable();
    expect($user->tokens()->count())->toBe(1);

    $this->withToken($response->json('token'))
        ->getJson('/api/management/me')
        ->assertOk()
        ->assertJsonFragment(['id' => $pairing->id, 'device_label' => 'iPhone']);
});

it('preserves a device label chosen in web settings during redemption', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $plainToken = $this->actingAs($user)
        ->postJson(route('settings.devices.pairings'), [
            'device_label' => 'Andrés Arbeitshandy',
        ])
        ->json('pairing_token');

    $this->postJson('/api/auth/pair', [
        'token' => $plainToken,
        'device_name' => 'eveplan iPhone',
    ])->assertOk();

    expect(DevicePairing::query()->sole()->device_label)->toBe('Andrés Arbeitshandy');
});

it('rejects expired pairing tokens and blocked accounts', function () {
    $approved = User::factory()->create(['is_approved' => true]);
    $expiredToken = str_repeat('a', 64);
    DevicePairing::create([
        'user_id' => $approved->id,
        'token_hash' => hash('sha256', $expiredToken),
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/pair', [
        'token' => $expiredToken,
        'device_name' => 'Expired',
    ])->assertUnprocessable();

    $blocked = User::factory()->create(['is_approved' => false]);
    $blockedToken = str_repeat('b', 64);
    DevicePairing::create([
        'user_id' => $blocked->id,
        'token_hash' => hash('sha256', $blockedToken),
        'expires_at' => now()->addMinutes(10),
    ]);
    $this->postJson('/api/auth/pair', [
        'token' => $blockedToken,
        'device_name' => 'Blocked',
    ])->assertForbidden();
});

it('revokes only the selected device token and forbids foreign revocation', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $other = User::factory()->create(['is_approved' => true]);
    $first = $user->createToken('Phone one', ['management:*'])->accessToken;
    $second = $user->createToken('Phone two', ['management:*'])->accessToken;
    $firstPairing = DevicePairing::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', str_repeat('c', 64)),
        'device_label' => 'Phone one',
        'expires_at' => now(),
        'redeemed_at' => now(),
        'personal_access_token_id' => $first->id,
    ]);
    $secondPairing = DevicePairing::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', str_repeat('d', 64)),
        'device_label' => 'Phone two',
        'expires_at' => now(),
        'redeemed_at' => now(),
        'personal_access_token_id' => $second->id,
    ]);
    $firstPush = PushToken::create([
        'user_id' => $user->id,
        'personal_access_token_id' => $first->id,
        'expo_token' => 'ExpoPushToken[first-phone]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);
    $secondPush = PushToken::create([
        'user_id' => $user->id,
        'personal_access_token_id' => $second->id,
        'expo_token' => 'ExpoPushToken[second-phone]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $this->actingAs($other)
        ->deleteJson(route('settings.devices.destroy', $firstPairing))
        ->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->actingAs($user)
        ->deleteJson(route('settings.devices.destroy', $firstPairing))
        ->assertNoContent();

    expect(PersonalAccessToken::whereKey($first->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::whereKey($second->id)->exists())->toBeTrue()
        ->and(PushToken::whereKey($firstPush->id)->exists())->toBeFalse()
        ->and(PushToken::whereKey($secondPush->id)->exists())->toBeTrue()
        ->and(DevicePairing::whereKey($firstPairing->id)->exists())->toBeFalse()
        ->and(DevicePairing::whereKey($secondPairing->id)->exists())->toBeTrue();
});

it('does not let a management bearer mint another pairing challenge', function () {
    $user = User::factory()->create(['is_approved' => true]);

    $this->withToken(managementTokenFor($user))
        ->postJson('/api/management/me/pairings')
        ->assertNotFound();
});

it('rate limits pairing redemption attempts', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.77']);

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $this->postJson('/api/auth/pair', [
            'token' => str_pad((string) $attempt, 64, 'x'),
            'device_name' => 'Unknown',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/auth/pair', [
        'token' => str_repeat('z', 64),
        'device_name' => 'Unknown',
    ])->assertStatus(429);
});
