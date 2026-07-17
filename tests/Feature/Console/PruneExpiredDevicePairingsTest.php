<?php

use App\Models\DevicePairing;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('prunes only expired pending pairing challenges after the grace period', function () {
    config()->set('retention.expired_device_pairings_hours', 24);
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'owner')->create();
    $old = DevicePairing::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'token_hash' => hash('sha256', 'old'),
        'expires_at' => now()->subHours(25),
    ]);
    $recent = DevicePairing::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'token_hash' => hash('sha256', 'recent'),
        'expires_at' => now()->subHours(23),
    ]);
    $redeemed = DevicePairing::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'token_hash' => null,
        'expires_at' => now()->subDays(2),
        'redeemed_at' => now()->subDays(3),
    ]);

    Artisan::call('app:prune-device-pairings');

    expect($old->fresh())->toBeNull()
        ->and($recent->fresh())->not->toBeNull()
        ->and($redeemed->fresh())->not->toBeNull();
});

it('supports a non-destructive pairing-prune dry run', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'owner')->create();
    $pairing = DevicePairing::create([
        'user_id' => $user->id,
        'event_id' => $event->id,
        'token_hash' => hash('sha256', 'dry-run'),
        'expires_at' => now()->subDays(2),
    ]);

    Artisan::call('app:prune-device-pairings', ['--dry-run' => true]);

    expect($pairing->fresh())->not->toBeNull()
        ->and(Artisan::output())->toContain('Would delete 1 expired pairing challenge');
});

it('schedules pairing and failed-job retention cleanup', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())
        ->toContain('app:prune-device-pairings')
        ->toContain('queue:prune-failed --hours=168');
});
