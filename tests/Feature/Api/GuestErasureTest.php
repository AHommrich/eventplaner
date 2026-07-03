<?php

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Support\Carbon;

it('schedules erasure, returns a recovery token, and revokes the current session', function () {
    config(['retention.guest_erasure_grace_days' => 30]);
    Carbon::setTestNow('2026-07-03 12:00:00');

    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $response = actingAsGuest($guest)->postJson('/api/guest/erasure')
        ->assertStatus(201)
        ->assertJsonStructure(['scheduled_erasure_at', 'can_revoke_until', 'recovery_token']);

    $guest->refresh();
    expect($guest->scheduled_erasure_at?->toIso8601String())
        ->toBe(Carbon::parse('2026-08-02 12:00:00')->toIso8601String());
    expect($guest->erasure_recovery_token)->not->toBeNull();
    expect($guest->tokens()->count())->toBe(0);

    // The plaintext token must NOT be stored — only its hash.
    $plain = $response->json('recovery_token');
    expect($guest->erasure_recovery_token)->toBe(hash('sha256', $plain));

    Carbon::setTestNow();
});

it('rejects a second erasure request with 409', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create([
        'event_id' => $event->id,
        'scheduled_erasure_at' => now()->addDays(10),
        'erasure_requested_at' => now(),
        'erasure_recovery_token' => hash('sha256', 'anything'),
    ]);

    actingAsGuest($guest)->postJson('/api/guest/erasure')
        ->assertStatus(409)
        ->assertJsonPath('scheduled_erasure_at', $guest->scheduled_erasure_at->toIso8601String());
});

it('revokes a scheduled erasure with the correct recovery token', function () {
    $event = Event::factory()->create();
    $plain = 'a-very-long-random-token-goes-here-1234567890';
    $guest = Guest::factory()->create([
        'event_id' => $event->id,
        'scheduled_erasure_at' => now()->addDays(20),
        'erasure_requested_at' => now(),
        'erasure_recovery_token' => hash('sha256', $plain),
    ]);

    $this->postJson('/api/guest/erasure/revoke', ['recovery_token' => $plain])
        ->assertOk()
        ->assertJson(['success' => true]);

    $guest->refresh();
    expect($guest->scheduled_erasure_at)->toBeNull();
    expect($guest->erasure_requested_at)->toBeNull();
    expect($guest->erasure_recovery_token)->toBeNull();
});

it('rejects revoke with an unknown token', function () {
    $this->postJson('/api/guest/erasure/revoke', ['recovery_token' => 'nope'])
        ->assertStatus(403);
});

it('rejects revoke after the grace window has passed with 410', function () {
    $event = Event::factory()->create();
    $plain = 'expired-window-token-xxxxxxxxxxxxxxxxxxxxxxx';
    Guest::factory()->create([
        'event_id' => $event->id,
        'scheduled_erasure_at' => now()->subDay(),
        'erasure_requested_at' => now()->subDays(31),
        'erasure_recovery_token' => hash('sha256', $plain),
    ]);

    $this->postJson('/api/guest/erasure/revoke', ['recovery_token' => $plain])
        ->assertStatus(410);
});

it('is scoped to the authenticated guest — cannot delete another guests data', function () {
    $event = Event::factory()->create();
    $me = Guest::factory()->create(['event_id' => $event->id]);
    $someoneElse = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($me)->postJson('/api/guest/erasure')->assertStatus(201);

    $me->refresh();
    $someoneElse->refresh();
    expect($me->scheduled_erasure_at)->not->toBeNull();
    expect($someoneElse->scheduled_erasure_at)->toBeNull();
});

it('purge command deletes guests past the grace window', function () {
    $event = Event::factory()->create();
    $due = Guest::factory()->create([
        'event_id' => $event->id,
        'scheduled_erasure_at' => now()->subDay(),
    ]);
    $notYet = Guest::factory()->create([
        'event_id' => $event->id,
        'scheduled_erasure_at' => now()->addDay(),
    ]);
    $noRequest = Guest::factory()->create(['event_id' => $event->id]);

    $this->artisan('guests:purge-expired-erasures')->assertSuccessful();

    expect(Guest::find($due->id))->toBeNull();
    expect(Guest::find($notYet->id))->not->toBeNull();
    expect(Guest::find($noRequest->id))->not->toBeNull();
});
