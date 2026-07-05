/**
 * Palette + role resolution for the event color system.
 *
 * The event stores three palette hexes (primary/secondary/tertiary) and nine
 * role fields that reference a palette key by name instead of a hex value.
 * When the palette shifts, every role that references it moves with it —
 * that is the whole point of the two-layer model.
 *
 * `buildPalette` fills in the wedding-default hexes when a slot is empty;
 * `resolveRole` looks up a role key against that palette and falls back to
 * a caller-defined key when the role has never been chosen (or references a
 * key that is not in the palette).
 *
 * Extracted from resources/js/pages/Event/Settings.vue so the logic can be
 * unit-tested and re-used (a future ColorSystemEditor sub-component, or the
 * RN app which currently duplicates the same rules).
 */

export type PaletteKey = 'primary' | 'secondary' | 'tertiary';

export type Palette = Record<PaletteKey, string>;

/** Wedding defaults — Bordeaux / Beige / White. */
export const DEFAULT_PALETTE: Palette = {
    primary: '#7c2d3e',
    secondary: '#e8e3de',
    tertiary: '#ffffff',
};

export function buildPalette(
    primary?: string | null,
    secondary?: string | null,
    tertiary?: string | null,
): Palette {
    return {
        primary: primary || DEFAULT_PALETTE.primary,
        secondary: secondary || DEFAULT_PALETTE.secondary,
        tertiary: tertiary || DEFAULT_PALETTE.tertiary,
    };
}

export function resolveRole(
    role: string | null | undefined,
    fallback: PaletteKey,
    palette: Palette,
): string {
    const key = ((role as PaletteKey) ?? fallback) as PaletteKey;
    return palette[key] ?? palette[fallback];
}
