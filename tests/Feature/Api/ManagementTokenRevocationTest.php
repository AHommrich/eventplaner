<?php

use App\Models\Event;
use App\Models\User;

it('revokes all User tokens when event access is removed', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $manager->createToken('phone', ['management:*']);
    $manager->createToken('tablet', ['management:*']);

    $this->actingAs($owner)
        ->withSession(['active_event_id' => $event->id])
        ->delete(route('event.access.remove', $manager))
        ->assertRedirect();

    expect($manager->tokens()->count())->toBe(0)
        ->and($event->users()->where('users.id', $manager->id)->exists())->toBeFalse();
});

it('revokes all User tokens when an existing event role changes', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $manager->createToken('phone', ['management:*']);

    $this->actingAs($owner)
        ->withSession(['active_event_id' => $event->id])
        ->patch(route('event.access.role', $manager), ['role' => 'event_admin'])
        ->assertRedirect();

    expect($manager->tokens()->count())->toBe(0)
        ->and($manager->fresh()->roleOn($event))->toBe('event_admin');
});

it('does not revoke tokens when a foreign user is submitted for removal', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $foreign = User::factory()->create(['is_approved' => true]);
    $foreign->createToken('phone', ['management:*']);

    $this->actingAs($owner)
        ->withSession(['active_event_id' => $event->id])
        ->delete(route('event.access.remove', $foreign))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($foreign->tokens()->count())->toBe(1);
});

it('revokes all User tokens when a platform admin removes approval', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['is_approved' => true]);
    $user->createToken('phone', ['management:*']);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), ['is_approved' => false])
        ->assertRedirect();

    expect($user->fresh()->is_approved)->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
});

it('revokes all User tokens when a platform role changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user', 'is_approved' => true]);
    $user->createToken('phone', ['management:*']);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), ['role' => 'admin'])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe('admin')
        ->and($user->tokens()->count())->toBe(0);
});

it('does not demote the last platform administrator', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson(route('admin.users.update', $admin), ['role' => 'user'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($admin->fresh()->role)->toBe('admin');
});

it('revokes orphan-prone Sanctum tokens when a User account is deleted', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $tokenId = $user->createToken('phone', ['management:*'])->accessToken->id;

    $user->delete();

    expect(\Laravel\Sanctum\PersonalAccessToken::whereKey($tokenId)->exists())->toBeFalse();
});
