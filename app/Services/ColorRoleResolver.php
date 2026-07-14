<?php

namespace App\Services;

use App\Models\Event;

/**
 * Resolves the palette + roles color system of an event into ready-to-use hex values.
 *
 * Data model:
 *  - 3 palette fields on the event — `color_primary` / `color_secondary` / `color_tertiary` (hex)
 *  - 10 role fields on the event   — store keys (`primary` | `secondary` | `tertiary`),
 *                                    NOT hex values. So on a palette change
 *                                    every role follows automatically.
 *
 * This class does the mapping once and returns API-ready hex strings.
 */
class ColorRoleResolver
{
    public const DEFAULT_PRIMARY = '#7c2d3e';

    public const DEFAULT_SECONDARY = '#e8e3de';

    public const DEFAULT_TERTIARY = '#ffffff';

    /**
     * Default fallback per role (palette key, NOT hex).
     *
     * @var array<string, string>
     */
    private const ROLE_DEFAULTS = [
        'role_screen_bg' => 'secondary',
        'role_card_bg' => 'tertiary',
        'role_card_text' => 'primary',
        'role_card_button' => 'primary',
        'role_card_button_text' => 'tertiary',
        'role_tab_tint' => 'primary',
        'role_border' => 'primary',
        'role_fab' => 'primary',
        'role_fab_icon' => 'tertiary',
        // Bottom tab bar background. Default `secondary` (screen tone) so classic
        // events look unchanged; the tab-tint role stays the foreground.
        'role_nav_bg' => 'secondary',
    ];

    /**
     * @return array{
     *   palette: array<string, string>,
     *   roles: array<string, string>,
     *   cover: array{home_text: ?string, home_shadow: string, home_shadow_opacity: int}
     * }
     */
    public function resolve(Event $event): array
    {
        $palette = [
            'primary' => $event->color_primary ?? self::DEFAULT_PRIMARY,
            'secondary' => $event->color_secondary ?? self::DEFAULT_SECONDARY,
            'tertiary' => $event->color_tertiary ?? self::DEFAULT_TERTIARY,
        ];

        $roles = [];
        foreach (self::ROLE_DEFAULTS as $field => $defaultKey) {
            $key = $event->$field ?? $defaultKey;
            // defensive: an invalid key falls back to the default, not to null.
            $roles[$field] = $palette[$key] ?? $palette[$defaultKey];
        }

        return [
            'palette' => $palette,
            'roles' => $roles,
            'cover' => [
                'home_text' => $event->color_home_text,
                'home_shadow' => $event->color_home_shadow ?? '#000000',
                'home_shadow_opacity' => $event->home_shadow_opacity ?? 50,
            ],
        ];
    }
}
