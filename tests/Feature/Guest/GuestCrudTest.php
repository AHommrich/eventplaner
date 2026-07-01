<?php

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use App\Models\DrinkLog;
use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;

it('creates a new guest in the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('guests.store'), [
        'firstname' => 'Lena',
        'lastname' => 'Mustermann',
    ])->assertRedirect();

    expect(Guest::where('event_id', $event->id)->where('firstname', 'Lena')->exists())->toBeTrue();
});

it('creates a guest under a group and uses group name as lastname fallback', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $group = Group::factory()->create(['event_id' => $event->id, 'name' => 'Müller']);

    $this->post(route('guests.store'), [
        'firstname' => 'Anna',
        'group_id' => $group->id,
    ])->assertRedirect();

    $guest = Guest::where('event_id', $event->id)->where('firstname', 'Anna')->first();
    expect($guest->lastname)->toBe('Müller');
    expect($guest->group_id)->toBe($group->id);
});

it('updates an existing guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'firstname' => 'Old']);

    $this->put(route('guests.update', $guest), [
        'firstname' => 'New Name',
        'group_id' => null,
    ])->assertRedirect();

    expect($guest->fresh()->firstname)->toBe('New Name');
});

it('deletes a guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $this->delete(route('guests.destroy', $guest))->assertRedirect();

    expect(Guest::find($guest->id))->toBeNull();
});

it('lets the owner set rsvp for a guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $this->post(route('guests.admin-rsvp', $guest), ['rsvp_status' => 'accepted'])
        ->assertRedirect();

    $fresh = $guest->fresh();
    expect($fresh->rsvp_status)->toBe('accepted');
    expect($fresh->rsvp_set_by_user_id)->toBe($user->id);
});

it('toggles app_access for a guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'app_access' => true]);

    $this->patch(route('guests.app-access', $guest), ['app_access' => false])
        ->assertRedirect();

    expect($guest->fresh()->app_access)->toBeFalse();
});

it('toggles drinks_access for a guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id, 'drinks_access' => true]);

    $this->patch(route('guests.drinks-access', $guest), ['drinks_access' => false])
        ->assertRedirect();

    expect($guest->fresh()->drinks_access)->toBeFalse();
});

it('rejects rsvp/access changes for guests from other events', function () {
    $user = actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreign = Guest::factory()->create(['event_id' => $otherEvent->id]);

    $this->post(route('guests.admin-rsvp', $foreign), ['rsvp_status' => 'accepted'])
        ->assertStatus(403);
});

it('resets drink logs for a guest', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $catalog = DrinkCatalog::factory()->create();
    $size = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 0]);
    $drink = Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);
    DrinkLog::create(['guest_id' => $guest->id, 'drink_id' => $drink->id, 'size_id' => $size->id, 'amount_liter' => 0.5, 'base_points' => 25, 'final_points' => 25]);
    DrinkLog::create(['guest_id' => $guest->id, 'drink_id' => $drink->id, 'size_id' => $size->id, 'amount_liter' => 0.5, 'base_points' => 25, 'final_points' => 25]);

    $this->delete(route('guests.drink-logs.reset', $guest))->assertRedirect();

    expect(DrinkLog::where('guest_id', $guest->id)->count())->toBe(0);
});
