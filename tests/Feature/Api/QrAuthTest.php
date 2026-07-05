<?php

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use Laravel\Sanctum\PersonalAccessToken;

it('returns a token immediately for solo guests', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $token = InvitationToken::factory()->create(['guest_id' => $guest->id]);

    $response = $this->getJson("/api/auth/qr/{$token->token}")
        ->assertOk()
        ->assertJsonPath('type', 'solo');

    expect($response->json('guests.0.token'))->toBeString()->not->toBeEmpty();
    expect($response->json('guests.0.guest_id'))->toBe($guest->id);
});

it('returns the member list without tokens for family guests', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    Guest::factory()->count(3)->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $token = InvitationToken::factory()->create(['group_id' => $group->id]);

    $response = $this->getJson("/api/auth/qr/{$token->token}")
        ->assertOk()
        ->assertJsonPath('type', 'family')
        ->assertJsonCount(3, 'guests');

    foreach ($response->json('guests') as $g) {
        expect($g['token'])->toBeNull();
        expect($g['is_active'])->toBeFalse();
    }
});

it('returns a token only for the chosen family member after select', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $guests = Guest::factory()->count(2)->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $token = InvitationToken::factory()->create(['group_id' => $group->id]);

    $response = $this->postJson("/api/auth/qr/{$token->token}/select", [
        'guest_id' => $guests[0]->id,
    ])->assertOk();

    expect($response->json('guest_id'))->toBe($guests[0]->id);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    // make sure only the chosen guest has a token
    expect(PersonalAccessToken::where('tokenable_type', Guest::class)->where('tokenable_id', $guests[0]->id)->count())->toBe(1);
    expect(PersonalAccessToken::where('tokenable_type', Guest::class)->where('tokenable_id', $guests[1]->id)->count())->toBe(0);
});

it('returns 409 when the chosen family member is already logged in', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $guest->createToken('first-login'); // already logged in
    $token = InvitationToken::factory()->create(['group_id' => $group->id]);

    $this->postJson("/api/auth/qr/{$token->token}/select", ['guest_id' => $guest->id])
        ->assertStatus(409);
});

it('logs out and deletes the current token', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->deleteJson('/api/auth/logout')->assertOk();

    expect(PersonalAccessToken::where('tokenable_type', Guest::class)->where('tokenable_id', $guest->id)->count())->toBe(0);
});

it('returns 404 for an invalid token', function () {
    $this->getJson('/api/auth/qr/nope-not-a-real-token')->assertStatus(404);
});

it('issues guest tokens with an explicit expiry (90 days by default)', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $token = InvitationToken::factory()->create(['guest_id' => $guest->id]);

    $this->getJson("/api/auth/qr/{$token->token}")->assertOk();

    $pat = PersonalAccessToken::where('tokenable_type', Guest::class)
        ->where('tokenable_id', $guest->id)
        ->first();

    expect($pat)->not->toBeNull();
    expect($pat->expires_at)->not->toBeNull();
    expect($pat->expires_at->diffInDays(now(), false))->toBeGreaterThan(-91)->toBeLessThan(-89);
});

it('does not treat expired tokens as active — re-login allowed', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $token = InvitationToken::factory()->create(['group_id' => $group->id]);

    // simulate a stale token from a previous event
    $guest->createToken('stale', ['role:guest'], now()->subDay());

    $response = $this->getJson("/api/auth/qr/{$token->token}")->assertOk();
    $memberSelf = collect($response->json('guests'))->firstWhere('guest_id', $guest->id);
    expect($memberSelf['is_active'])->toBeFalse();

    // select must succeed — no 409
    $this->postJson("/api/auth/qr/{$token->token}/select", ['guest_id' => $guest->id])
        ->assertOk();
});

it('rejects login when app_access is disabled for all guests in group', function () {
    $event = Event::factory()->create();
    $group = Group::factory()->create(['event_id' => $event->id]);
    Guest::factory()->count(2)->withoutAppAccess()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    $token = InvitationToken::factory()->create(['group_id' => $group->id]);

    $this->getJson("/api/auth/qr/{$token->token}")->assertStatus(403);
});
