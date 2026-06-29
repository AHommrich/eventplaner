<?php

use App\Models\Event;
use App\Models\EventStylePreset;

it('saves a new style preset', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('event.style-presets.store'), [
        'name'            => 'Bordeaux Classic',
        'color_primary'   => '#7c2d3e',
        'color_secondary' => '#e8e3de',
        'color_tertiary'  => '#ffffff',
    ])->assertRedirect();

    expect(EventStylePreset::where('event_id', $event->id)->where('name', 'Bordeaux Classic')->exists())->toBeTrue();
});

it('deletes a style preset', function () {
    $user   = actingAsOwner();
    $event  = $user->ownedEvents()->first();
    $preset = EventStylePreset::create(['event_id' => $event->id, 'name' => 'X', 'color_primary' => '#000000']);

    $this->delete(route('event.style-presets.destroy', $preset))->assertRedirect();

    expect(EventStylePreset::find($preset->id))->toBeNull();
});

it('rejects deleting a preset of another event', function () {
    actingAsOwner();
    $otherEvent   = Event::factory()->create();
    $foreignPreset = EventStylePreset::create(['event_id' => $otherEvent->id, 'name' => 'foreign', 'color_primary' => '#fff']);

    $this->delete(route('event.style-presets.destroy', $foreignPreset))->assertStatus(403);
});
