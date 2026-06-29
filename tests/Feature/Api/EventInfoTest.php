<?php

use App\Models\Event;
use App\Models\Guest;

it('returns palette and resolved role colors as hex', function () {
    $event = Event::factory()->create([
        'color_primary'   => '#aa0000',
        'color_secondary' => '#00bb00',
        'color_tertiary'  => '#0000cc',
        'role_screen_bg'  => 'secondary',
        'role_card_bg'    => 'tertiary',
        'role_card_text'  => 'primary',
    ]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJson([
            'color_primary'   => '#aa0000',
            'color_secondary' => '#00bb00',
            'color_tertiary'  => '#0000cc',
            'color_screen_bg' => '#00bb00',
            'color_card'      => '#0000cc',
            'color_card_text' => '#aa0000',
        ]);
});

it('returns null cover home_text when no cover is set', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonPath('color_home_text', null);
});

it('returns set cover overlay fields when cover is uploaded', function () {
    $event = Event::factory()->create([
        'cover_image_url'     => 'https://r2.example/cover.jpg',
        'color_home_text'     => '#ffffff',
        'color_home_shadow'   => '#222222',
        'home_shadow_opacity' => 70,
    ]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJson([
            'cover_image_url'     => 'https://r2.example/cover.jpg',
            'color_home_text'     => '#ffffff',
            'color_home_shadow'   => '#222222',
            'home_shadow_opacity' => 70,
        ]);
});

it('assembles structured german venue address', function () {
    $event = Event::factory()->create([
        'venue_name'         => 'Schloss Drachenburg',
        'venue_street'       => 'Drachenfelsstraße',
        'venue_house_number' => '118',
        'venue_postal_code'  => '53639',
        'venue_city'         => 'Königswinter',
        'venue_country'      => 'Deutschland',
    ]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonPath('venue_address', 'Drachenfelsstraße 118, 53639 Königswinter');
});

it('falls back to legacy venue_address when no structured address exists', function () {
    $event = Event::factory()->create([
        'venue_address' => 'Old-Style Freitext Adresse 42',
    ]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonPath('venue_address', 'Old-Style Freitext Adresse 42');
});
