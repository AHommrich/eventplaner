<?php

use App\Models\User;

it('shows the event access page with current members', function () {
    actingAsOwner();

    $this->get(route('event.access'))->assertOk();
});

it('invites another user to the event by email', function () {
    $user    = actingAsOwner();
    $event   = $user->ownedEvents()->first();
    $invitee = User::factory()->create(['email' => 'invitee@test.de']);

    $this->post(route('event.access.invite'), ['email' => 'invitee@test.de'])
        ->assertRedirect();

    expect($event->fresh()->users()->where('users.id', $invitee->id)->exists())->toBeTrue();
});

it('removes a user from the event', function () {
    $user    = actingAsOwner();
    $event   = $user->ownedEvents()->first();
    $other   = User::factory()->create();
    $event->users()->attach($other->id);

    $this->delete(route('event.access.remove', $other))->assertRedirect();

    expect($event->fresh()->users()->where('users.id', $other->id)->exists())->toBeFalse();
});

it('rejects removing the event owner', function () {
    $user  = actingAsOwner();

    $this->delete(route('event.access.remove', $user))
        ->assertRedirect()
        ->assertSessionHas('error');
});
