<?php

namespace App\Services;

use App\Models\Event;

/**
 * Löst das Palette-+-Rollen-Farbsystem eines Events zu fertigen Hex-Werten auf.
 *
 * Datenmodell:
 *  - 3 Palette-Felder am Event   — `color_primary` / `color_secondary` / `color_tertiary` (Hex)
 *  - 9 Rollen-Felder am Event    — speichern Keys (`primary` | `secondary` | `tertiary`),
 *                                  NICHT Hex-Werte. So folgt bei einem Palette-Wechsel
 *                                  jede Rolle automatisch nach.
 *
 * Diese Klasse macht das Mapping nur einmal und liefert API-fertige Hex-Strings.
 */
class ColorRoleResolver
{
    public const DEFAULT_PRIMARY = '#7c2d3e';

    public const DEFAULT_SECONDARY = '#e8e3de';

    public const DEFAULT_TERTIARY = '#ffffff';

    /**
     * Default-Fallback je Rolle (Key der Palette, NICHT Hex).
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
            // Defensive: ungültiger Key fällt auf das Default zurück, nicht auf null.
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
