<?php

use App\Models\Event;
use App\Models\FoodSpecial;
use App\Models\Group;
use App\Models\Guest;

/**
 * P0.3: GuestController store/update must reject cross-event group / food-special
 * references (a write hole route-gating does not catch), while still accepting
 * the global food-special templates (event_id = null) valid for every event.
 */
it('rejects a foreign event group_id on create', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreignGroup = Group::factory()->create(['event_id' => $otherEvent->id]);

    $this->post(route('guests.store'), [
        'firstname' => 'Eve',
        'group_id' => $foreignGroup->id,
    ])->assertSessionHasErrors('group_id');

    expect(Guest::where('firstname', 'Eve')->exists())->toBeFalse();
});

it('rejects a foreign event private food-special on create', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreignFood = FoodSpecial::create(['event_id' => $otherEvent->id, 'name' => 'Geheim']);

    $this->post(route('guests.store'), [
        'firstname' => 'Eve',
        'food_specials' => [$foreignFood->id],
    ])->assertSessionHasErrors('food_specials.0');

    expect(Guest::where('firstname', 'Eve')->exists())->toBeFalse();
});

it('accepts a global food-special template on create', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $template = FoodSpecial::create(['event_id' => null, 'name' => 'Vegan']);

    $this->post(route('guests.store'), [
        'firstname' => 'Ann',
        'food_specials' => [$template->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $guest = Guest::where('event_id', $event->id)->where('firstname', 'Ann')->first();
    expect($guest->foodSpecials->pluck('id')->all())->toContain($template->id);
});

it('accepts an own event-local food-special on create', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $local = FoodSpecial::create(['event_id' => $event->id, 'name' => 'Nussallergie']);

    $this->post(route('guests.store'), [
        'firstname' => 'Ben',
        'food_specials' => [$local->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Guest::where('event_id', $event->id)->where('firstname', 'Ben')->exists())->toBeTrue();
});

it('rejects a foreign event food-special on update', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $otherEvent = Event::factory()->create();
    $foreignFood = FoodSpecial::create(['event_id' => $otherEvent->id, 'name' => 'Geheim']);

    $this->put(route('guests.update', $guest), [
        'firstname' => 'Updated',
        'food_specials' => [$foreignFood->id],
    ])->assertSessionHasErrors('food_specials.0');
});
