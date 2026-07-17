<?php

use App\Models\Event;
use App\Models\User;

/** Create a member on an event at a given pivot tier. */
function memberWithRole(Event $event, string $role): User
{
    $user = User::factory()->create();
    $event->users()->attach($user->id, ['role' => $role]);

    return $user;
}

it('resolves roleOn with the correct precedence', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();

    $admin = User::factory()->create(['role' => 'admin']);
    $coOwner = memberWithRole($event, 'owner');
    $eventAdmin = memberWithRole($event, 'event_admin');
    $manager = memberWithRole($event, 'event_manager');
    $stranger = User::factory()->create();

    expect($admin->roleOn($event))->toBe('superadmin');
    expect($owner->roleOn($event))->toBe('owner');
    expect($coOwner->roleOn($event))->toBe('owner');
    expect($eventAdmin->roleOn($event))->toBe('event_admin');
    expect($manager->roleOn($event))->toBe('event_manager');
    expect($stranger->roleOn($event))->toBeNull();
});

it('collects primary and pivot owners via owners()/isOwnedBy()', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $coOwner = memberWithRole($event, 'owner');
    $manager = memberWithRole($event, 'event_manager');

    $ownerIds = $event->owners()->pluck('id')->sort()->values()->all();

    expect($ownerIds)->toBe(collect([$owner->id, $coOwner->id])->sort()->values()->all());
    expect($event->isOwnedBy($owner))->toBeTrue();
    expect($event->isOwnedBy($coOwner))->toBeTrue();
    expect($event->isOwnedBy($manager))->toBeFalse();
});

it('applies the manage/administer gates per tier', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $eventAdmin = memberWithRole($event, 'event_admin');
    $manager = memberWithRole($event, 'event_manager');
    $stranger = User::factory()->create();

    // manage: manager ∪ event_admin ∪ owner
    expect($owner->can('manage', $event))->toBeTrue();
    expect($eventAdmin->can('manage', $event))->toBeTrue();
    expect($manager->can('manage', $event))->toBeTrue();
    expect($stranger->can('manage', $event))->toBeFalse();

    // administer: event_admin ∪ owner (manager excluded)
    expect($owner->can('administer', $event))->toBeTrue();
    expect($eventAdmin->can('administer', $event))->toBeTrue();
    expect($manager->can('administer', $event))->toBeFalse();
    expect($stranger->can('administer', $event))->toBeFalse();
});

it('lets a superadmin pass the coarse gates but not blanket-grant fine ones', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $admin = User::factory()->create(['role' => 'admin']);

    expect($admin->can('manage', $event))->toBeTrue();
    expect($admin->can('administer', $event))->toBeTrue();
    expect($admin->can('manageAccess', $event))->toBeTrue();
    // fine-grained still resolve (superadmin is allowed by their own rules)
    expect($admin->can('deleteEvent', $event))->toBeTrue();
    expect($admin->can('grantOwner', $event))->toBeTrue();
});

it('enforces changeAccess target rules', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $eventAdmin = memberWithRole($event, 'event_admin');
    $manager = memberWithRole($event, 'event_manager');

    // owner may assign any tier
    expect($owner->can('changeAccess', [$event, null, 'event_manager']))->toBeTrue();
    expect($owner->can('changeAccess', [$event, null, 'event_admin']))->toBeTrue();
    expect($owner->can('changeAccess', [$event, null, 'owner']))->toBeTrue();

    // event_admin may only assign event_manager
    expect($eventAdmin->can('changeAccess', [$event, null, 'event_manager']))->toBeTrue();
    expect($eventAdmin->can('changeAccess', [$event, null, 'event_admin']))->toBeFalse();
    expect($eventAdmin->can('changeAccess', [$event, null, 'owner']))->toBeFalse();

    // manager may not touch access at all
    expect($manager->can('changeAccess', [$event, null, 'event_manager']))->toBeFalse();

    // the global admin tier is never assignable
    expect($owner->can('changeAccess', [$event, null, 'admin']))->toBeFalse();
});

it('enforces removeMember by target tier', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $eventAdmin = memberWithRole($event, 'event_admin');
    $manager = memberWithRole($event, 'event_manager');
    $coOwner = memberWithRole($event, 'owner');

    // owner may remove anyone
    expect($owner->can('removeMember', [$event, $manager]))->toBeTrue();
    expect($owner->can('removeMember', [$event, $eventAdmin]))->toBeTrue();

    // event_admin may remove only managers
    expect($eventAdmin->can('removeMember', [$event, $manager]))->toBeTrue();
    expect($eventAdmin->can('removeMember', [$event, $coOwner]))->toBeFalse();

    $anotherAdmin = memberWithRole($event, 'event_admin');
    expect($eventAdmin->can('removeMember', [$event, $anotherAdmin]))->toBeFalse();
});

it('restricts owner-only abilities to the owner tier', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $eventAdmin = memberWithRole($event, 'event_admin');

    foreach (['grantOwner', 'transferOwnership', 'deleteEvent'] as $ability) {
        expect($owner->can($ability, $event))->toBeTrue();
        expect($eventAdmin->can($ability, $event))->toBeFalse();
    }
});
