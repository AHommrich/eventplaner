<?php

use App\Models\Event;
use App\Models\PushToken;
use App\Models\User;
use App\Services\ManagementTokenService;
use Illuminate\Support\Carbon;

it('registers and refreshes an Expo token without requiring an event header', function () {
    Carbon::setTestNow('2026-07-17 12:00:00');
    $user = User::factory()->create(['is_approved' => true]);
    $expoToken = 'ExponentPushToken[device_one]';
    $bearer = managementTokenFor($user);
    $accessToken = $user->tokens()->sole();

    $this->withToken($bearer)
        ->postJson('/api/management/push/register', [
            'expo_token' => $expoToken,
            'platform' => 'ios',
        ])
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('platform', 'ios');

    Carbon::setTestNow('2026-07-17 12:10:00');
    $this->withToken($bearer)
        ->postJson('/api/management/push/register', [
            'expo_token' => $expoToken,
            'platform' => 'android',
        ])
        ->assertOk();

    expect(PushToken::query()->count())->toBe(1);
    $this->assertDatabaseHas('push_tokens', [
        'user_id' => $user->id,
        'personal_access_token_id' => $accessToken->id,
        'expo_token' => $expoToken,
        'platform' => 'android',
        'last_used_at' => '2026-07-17 12:10:00',
    ]);
});

it('moves a device token to the current account', function () {
    $previous = User::factory()->create(['is_approved' => true]);
    $current = User::factory()->create(['is_approved' => true]);
    $expoToken = 'ExpoPushToken[shared-device]';
    $previousAccessToken = $previous->createToken('old phone', ['management:*'])->accessToken;
    PushToken::create([
        'user_id' => $previous->id,
        'personal_access_token_id' => $previousAccessToken->id,
        'expo_token' => $expoToken,
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);

    $currentBearer = managementTokenFor($current);
    $currentAccessToken = $current->tokens()->sole();
    $this->withToken($currentBearer)
        ->postJson('/api/management/push/register', [
            'expo_token' => $expoToken,
            'platform' => 'ios',
        ])
        ->assertOk();

    expect(PushToken::query()->sole()->user_id)->toBe($current->id)
        ->and(PushToken::query()->sole()->personal_access_token_id)->toBe($currentAccessToken->id);
});

it('lets a user opt out without deleting another users token', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $other = User::factory()->create(['is_approved' => true]);
    $bearer = managementTokenFor($user);
    $ownAccessToken = $user->tokens()->sole();
    $foreignAccessToken = $other->createToken('foreign phone', ['management:*'])->accessToken;
    $own = PushToken::create([
        'user_id' => $user->id,
        'personal_access_token_id' => $ownAccessToken->id,
        'expo_token' => 'ExponentPushToken[own-device]',
        'platform' => 'ios',
        'last_used_at' => now(),
    ]);
    $foreign = PushToken::create([
        'user_id' => $other->id,
        'personal_access_token_id' => $foreignAccessToken->id,
        'expo_token' => 'ExponentPushToken[foreign-device]',
        'platform' => 'android',
        'last_used_at' => now(),
    ]);

    $client = $this->withToken($bearer);
    $client->postJson('/api/management/push/register', [
        'expo_token' => $foreign->expo_token,
        'platform' => 'android',
        'enabled' => false,
    ])->assertOk()->assertJsonPath('enabled', false);
    $client->postJson('/api/management/push/register', [
        'expo_token' => $own->expo_token,
        'platform' => 'ios',
        'enabled' => false,
    ])->assertOk()->assertJsonPath('enabled', false);

    $this->assertDatabaseMissing('push_tokens', ['id' => $own->id]);
    $this->assertDatabaseHas('push_tokens', ['id' => $foreign->id]);
});

it('replaces a rotated Expo token within the same device session', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $bearer = managementTokenFor($user);

    $client = $this->withToken($bearer);
    $client->postJson('/api/management/push/register', [
        'expo_token' => 'ExpoPushToken[before-rotation]',
        'platform' => 'ios',
    ])->assertOk();
    $client->postJson('/api/management/push/register', [
        'expo_token' => 'ExpoPushToken[after-rotation]',
        'platform' => 'ios',
    ])->assertOk();

    expect(PushToken::query()->count())->toBe(1)
        ->and(PushToken::query()->sole()->expo_token)->toBe('ExpoPushToken[after-rotation]');
});

it('deletes the bound push token when its bearer is revoked', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $bearer = managementTokenFor($user);

    $this->withToken($bearer)->postJson('/api/management/push/register', [
        'expo_token' => 'ExpoPushToken[revoked-device]',
        'platform' => 'android',
    ])->assertOk();

    $user->tokens()->sole()->delete();

    expect(PushToken::query()->count())->toBe(0);
});

it('validates the Expo token and platform', function () {
    $user = User::factory()->create(['is_approved' => true]);

    $this->withToken(managementTokenFor($user))
        ->postJson('/api/management/push/register', [
            'expo_token' => 'not-an-expo-token',
            'platform' => 'web',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expo_token', 'platform']);
});

it('requires management authentication to register a push token', function () {
    $this->postJson('/api/management/push/register', [
        'expo_token' => 'ExponentPushToken[device]',
        'platform' => 'ios',
    ])->assertUnauthorized();
});

it('rejects stateful web auth because push registration must bind to a bearer', function () {
    $user = User::factory()->create(['is_approved' => true]);

    $this->actingAs($user)->postJson('/api/management/push/register', [
        'expo_token' => 'ExponentPushToken[stateful-web]',
        'platform' => 'ios',
    ])->assertForbidden();
});

it('cascades online logout through the device session and push token', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($user, 'owner')->create();
    $issued = app(ManagementTokenService::class)->issue($user, $event, 'Logout phone');

    $this->withToken($issued->plainTextToken)
        ->postJson('/api/management/push/register', [
            'expo_token' => 'ExponentPushToken[logout-device]',
            'platform' => 'ios',
        ])->assertOk();

    $this->withToken($issued->plainTextToken)
        ->deleteJson('/api/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0)
        ->and($user->devicePairings()->count())->toBe(0)
        ->and($user->pushTokens()->count())->toBe(0);
});
