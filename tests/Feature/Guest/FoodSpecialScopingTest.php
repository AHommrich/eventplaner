<?php

use App\Models\Event;
use App\Models\FoodSpecial;
use App\Models\Guest;

/**
 * P0.1 delta-model scoping for food_specials: reads resolve to
 * "global templates (event_id = null) ∪ own event-local" and never leak
 * another event's private entries.
 */
it('lists global templates and own event-local food specials, not foreign ones', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    $template = FoodSpecial::create(['event_id' => null, 'name' => 'Vegan']);
    $ownLocal = FoodSpecial::create(['event_id' => $event->id, 'name' => 'Nussallergie']);

    $otherEvent = Event::factory()->create();
    $foreignLocal = FoodSpecial::create(['event_id' => $otherEvent->id, 'name' => 'Geheim']);

    $this->get(route('guests.edit', $guest))
        ->assertInertia(function ($assert) use ($template, $ownLocal, $foreignLocal) {
            $ids = collect($assert->toArray()['props']['food_specials'])->pluck('id')->all();

            expect($ids)->toContain($template->id)
                ->toContain($ownLocal->id)
                ->not->toContain($foreignLocal->id);
        });
});
