<?php

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

it('issues a Sanctum token to an approved verified user with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'manager@example.com',
        'password' => Hash::make('correct-password'),
        'email_verified_at' => now(),
        'is_approved' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
        'device_name' => 'Andre phone',
    ])->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    expect(PersonalAccessToken::query()->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->exists())
        ->toBeTrue();
    expect($user->tokens()->first()->can('management:*'))->toBeTrue();
    $session = DevicePairing::query()->sole();
    expect($session->device_label)->toBe('Andre phone')
        ->and($session->personal_access_token_id)->toBe($user->tokens()->first()->id)
        ->and($user->tokens()->first()->expires_at->between(now()->addDays(89), now()->addDays(91)))->toBeTrue();

    $this->actingAs($user)
        ->get(route('settings.devices'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('devices.0.id', $session->id)
            ->where('devices.0.device_label', 'Andre phone'));
});

it('rejects invalid credentials without issuing a token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'is_approved' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

it('rejects an unverified user', function () {
    $user = User::factory()->unverified()->create([
        'password' => Hash::make('correct-password'),
        'is_approved' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertForbidden();

    expect($user->tokens()->count())->toBe(0);
});

it('rejects an unapproved non-admin user', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'is_approved' => false,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertForbidden();

    expect($user->tokens()->count())->toBe(0);
});

it('allows a verified superadmin regardless of the approval flag', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => Hash::make('correct-password'),
        'email_verified_at' => now(),
        'is_approved' => false,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'correct-password',
    ])->assertOk();
});

it('rate limits repeated management login attempts', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
