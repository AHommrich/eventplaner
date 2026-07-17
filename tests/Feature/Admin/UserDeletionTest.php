<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
 * P0.5 — user deletion must never destroy an event.
 *
 * `events.user_id` is now RESTRICT (not CASCADE), and Admin/UserController
 * guards deletion of a user who still owns an event. Shared-event memberships
 * (event_user) cascade away on their own.
 */

it('refuses to delete a user who owns an event and keeps the event intact', function () {
    actingAsAdmin();

    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();

    $response = $this->delete(route('admin.users.destroy', $owner));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
    $this->assertDatabaseHas('events', ['id' => $event->id]);
});

it('deletes a user with no owned events and cascades their shared membership', function () {
    actingAsAdmin();

    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();

    $member = User::factory()->create();
    $event->users()->attach($member->id);

    $response = $this->delete(route('admin.users.destroy', $member));

    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('users', ['id' => $member->id]);
    $this->assertDatabaseMissing('event_user', ['event_id' => $event->id, 'user_id' => $member->id]);
    // the event and its owner are untouched
    $this->assertDatabaseHas('events', ['id' => $event->id]);
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

it('refuses self-deletion', function () {
    $admin = actingAsAdmin();

    $response = $this->delete(route('admin.users.destroy', $admin));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('restricts deleting an event owner at the database level', function () {
    $owner = User::factory()->create();
    Event::factory()->for($owner, 'owner')->create();

    // The RESTRICT FK must reject the delete instead of cascading the event away.
    expect(fn () => $owner->delete())->toThrow(QueryException::class);
});
