<?php

use App\Models\Event;
use App\Models\EventStylePreset;

it('saves a new style preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('event.style-presets.store'), [
        'name' => 'Bordeaux Classic',
        'color_primary' => '#7c2d3e',
        'color_secondary' => '#e8e3de',
        'color_tertiary' => '#ffffff',
    ])->assertRedirect();

    expect(EventStylePreset::where('event_id', $event->id)->where('name', 'Bordeaux Classic')->exists())->toBeTrue();
});

it('saves the app design preset with a style preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('event.style-presets.store'), [
        'name' => 'Soft Luxury',
        'color_primary' => '#7c2d3e',
        'design_preset' => 'soft-luxury',
    ])->assertRedirect();

    expect(EventStylePreset::where('event_id', $event->id)->where('name', 'Soft Luxury')->value('design_preset'))
        ->toBe('soft-luxury');
});

it('rejects an invalid app design preset on a style preset', function () {
    actingAsOwner();

    $this->post(route('event.style-presets.store'), [
        'name' => 'Invalid',
        'design_preset' => 'unknown',
    ])->assertSessionHasErrors(['design_preset']);
});

it('keeps an explicitly chosen navbar role on a style preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('event.style-presets.store'), [
        'name' => 'Inverted cards',
        'color_primary' => '#112233',
        'role_card_bg' => 'primary',
        'role_nav_bg' => 'primary',
    ])->assertRedirect();

    expect(EventStylePreset::where('event_id', $event->id)->where('name', 'Inverted cards')->value('role_nav_bg'))
        ->toBe('primary');
});

it('updates an existing style preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $preset = EventStylePreset::create(['event_id' => $event->id, 'name' => 'Before', 'color_primary' => '#112233']);

    $this->put(route('event.style-presets.update', $preset), [
        'name' => 'After',
        'color_primary' => '#445566',
        'role_card_bg' => 'tertiary',
        'role_nav_bg' => 'tertiary',
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('After');
    expect($preset->fresh()->role_config_version)->toBe(2);
});

it('deletes a style preset', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $preset = EventStylePreset::create(['event_id' => $event->id, 'name' => 'X', 'color_primary' => '#000000']);

    $this->delete(route('event.style-presets.destroy', $preset))->assertRedirect();

    expect(EventStylePreset::find($preset->id))->toBeNull();
});

it('rejects deleting a preset of another event', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreignPreset = EventStylePreset::create(['event_id' => $otherEvent->id, 'name' => 'foreign', 'color_primary' => '#fff']);

    $this->delete(route('event.style-presets.destroy', $foreignPreset))->assertStatus(403);
});
