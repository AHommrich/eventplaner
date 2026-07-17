<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;

it('returns only the event bound to the current device without an event header', function () {
    $user = User::factory()->create(['is_approved' => true]);
    $owned = Event::factory()->for($user, 'owner')->create(['name' => 'Owned']);
    $shared = Event::factory()->create(['name' => 'Shared']);
    $foreign = Event::factory()->create(['name' => 'Foreign']);
    $shared->users()->attach($user, ['role' => 'event_admin']);

    $this->withToken(managementTokenFor($user, $shared))
        ->getJson('/api/management/me/events')
        ->assertOk()
        ->assertJsonFragment(['id' => $shared->id, 'name' => 'Shared', 'my_role' => 'event_admin'])
        ->assertJsonMissing(['id' => $owned->id])
        ->assertJsonMissing(['id' => $foreign->id]);
});

it('returns only the superadmins bound event with the superadmin role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $first = Event::factory()->create();
    $second = Event::factory()->create();

    $this->withToken(managementTokenFor($admin, $second))
        ->getJson('/api/management/me/events')
        ->assertOk()
        ->assertJsonFragment(['id' => $second->id, 'my_role' => 'superadmin'])
        ->assertJsonMissing(['id' => $first->id]);
});

it('requires management authentication but not X-Event-ID', function () {
    $this->getJson('/api/management/me/events')->assertUnauthorized();
});

it('exposes the exact guest theme contract for the bound management event', function (string $preset) {
    $user = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($user, 'owner')->create([
        'design_preset' => $preset,
        'color_primary' => '#112233',
        'color_secondary' => '#445566',
        'color_tertiary' => '#778899',
        'role_card_bg' => 'secondary',
        'role_nav_bg' => 'tertiary',
        'font_heading' => 'playfair',
    ]);
    $guest = Guest::factory()->for($event)->create(['app_access' => true]);
    $themeKeys = [
        'color_primary',
        'color_secondary',
        'color_tertiary',
        'color_screen_bg',
        'color_card',
        'color_card_text',
        'color_card_button',
        'color_card_button_text',
        'color_tab_tint',
        'color_border',
        'color_fab',
        'color_fab_icon',
        'color_nav_bg',
        'color_home_text',
        'color_home_shadow',
        'home_shadow_opacity',
        'font_heading',
        'design_preset',
    ];

    $guestTheme = collect(actingAsGuest($guest)->getJson('/api/event/info')->assertOk()->json())
        ->only($themeKeys)
        ->all();
    $this->app['auth']->forgetGuards();
    $managementTheme = $this->withToken(managementTokenFor($user, $event))
        ->getJson('/api/management/me/events')
        ->assertOk()
        ->json('events.0.theme');

    expect($managementTheme)->toBe($guestTheme);
})->with(['classic', 'soft-luxury']);
