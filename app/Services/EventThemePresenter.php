<?php

namespace App\Services;

use App\Models\Event;

/** One canonical event-theme payload shared by Guest and Organizer APIs. */
class EventThemePresenter
{
    public function __construct(private readonly ColorRoleResolver $colors) {}

    /** @return array<string, mixed> */
    public function present(Event $event): array
    {
        $resolved = $this->colors->resolve($event);

        return [
            'color_primary' => $resolved['palette']['primary'],
            'color_secondary' => $resolved['palette']['secondary'],
            'color_tertiary' => $resolved['palette']['tertiary'],
            'color_screen_bg' => $resolved['roles']['role_screen_bg'],
            'color_card' => $resolved['roles']['role_card_bg'],
            'color_card_text' => $resolved['roles']['role_card_text'],
            'color_card_button' => $resolved['roles']['role_card_button'],
            'color_card_button_text' => $resolved['roles']['role_card_button_text'],
            'color_tab_tint' => $resolved['roles']['role_tab_tint'],
            'color_border' => $resolved['roles']['role_border'],
            'color_fab' => $resolved['roles']['role_fab'],
            'color_fab_icon' => $resolved['roles']['role_fab_icon'],
            'color_nav_bg' => $resolved['roles']['role_nav_bg'],
            'color_home_text' => $resolved['cover']['home_text'],
            'color_home_shadow' => $resolved['cover']['home_shadow'],
            'home_shadow_opacity' => $resolved['cover']['home_shadow_opacity'],
            'font_heading' => $event->font_heading,
            'design_preset' => $event->design_preset ?? 'classic',
        ];
    }
}
