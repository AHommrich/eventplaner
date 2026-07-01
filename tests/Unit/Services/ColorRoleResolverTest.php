<?php

use App\Models\Event;
use App\Services\ColorRoleResolver;

function makeEvent(array $attrs = []): Event
{
    return new Event(array_merge([
        'color_primary' => '#aa0000',
        'color_secondary' => '#00bb00',
        'color_tertiary' => '#0000cc',
    ], $attrs));
}

it('resolves all nine roles to hex colors from palette', function () {
    $event = makeEvent([
        'role_screen_bg' => 'secondary',
        'role_card_bg' => 'tertiary',
        'role_card_text' => 'primary',
        'role_card_button' => 'primary',
        'role_card_button_text' => 'tertiary',
        'role_tab_tint' => 'primary',
        'role_border' => 'secondary',
        'role_fab' => 'primary',
        'role_fab_icon' => 'tertiary',
    ]);

    $resolved = (new ColorRoleResolver)->resolve($event);

    expect($resolved['roles'])->toBe([
        'role_screen_bg' => '#00bb00',
        'role_card_bg' => '#0000cc',
        'role_card_text' => '#aa0000',
        'role_card_button' => '#aa0000',
        'role_card_button_text' => '#0000cc',
        'role_tab_tint' => '#aa0000',
        'role_border' => '#00bb00',
        'role_fab' => '#aa0000',
        'role_fab_icon' => '#0000cc',
    ]);
});

it('falls back to role default when role field is null', function () {
    // no roles set — all fall back to their defaults.
    $event = makeEvent();

    $resolved = (new ColorRoleResolver)->resolve($event);

    // role_screen_bg default = 'secondary' → #00bb00
    expect($resolved['roles']['role_screen_bg'])->toBe('#00bb00');
    // role_card_bg default = 'tertiary' → #0000cc
    expect($resolved['roles']['role_card_bg'])->toBe('#0000cc');
    // role_card_text default = 'primary' → #aa0000
    expect($resolved['roles']['role_card_text'])->toBe('#aa0000');
});

it('falls back to default when role key is invalid', function () {
    $event = makeEvent(['role_screen_bg' => 'lila_die_es_nicht_gibt']);

    $resolved = (new ColorRoleResolver)->resolve($event);

    // invalid key → falls back to the default (secondary), not to null.
    expect($resolved['roles']['role_screen_bg'])->toBe('#00bb00');
});

it('uses palette defaults when palette fields are null', function () {
    $event = new Event; // everything null

    $resolved = (new ColorRoleResolver)->resolve($event);

    expect($resolved['palette'])->toBe([
        'primary' => ColorRoleResolver::DEFAULT_PRIMARY,
        'secondary' => ColorRoleResolver::DEFAULT_SECONDARY,
        'tertiary' => ColorRoleResolver::DEFAULT_TERTIARY,
    ]);
});

it('returns null cover home_text when no cover overlay is configured', function () {
    $event = makeEvent(); // color_home_text not set

    $resolved = (new ColorRoleResolver)->resolve($event);

    expect($resolved['cover']['home_text'])->toBeNull();
    expect($resolved['cover']['home_shadow'])->toBe('#000000'); // default
    expect($resolved['cover']['home_shadow_opacity'])->toBe(50); // default
});

it('returns set cover overlay fields when they exist', function () {
    $event = makeEvent([
        'color_home_text' => '#ffffff',
        'color_home_shadow' => '#333333',
        'home_shadow_opacity' => 75,
    ]);

    $resolved = (new ColorRoleResolver)->resolve($event);

    expect($resolved['cover'])->toBe([
        'home_text' => '#ffffff',
        'home_shadow' => '#333333',
        'home_shadow_opacity' => 75,
    ]);
});
