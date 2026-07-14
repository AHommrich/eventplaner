/**
 * Design presets — the backend-selectable "form language" layer.
 *
 * This is a VERBATIM mirror of the guest app's authoritative token table in
 * `eventplaner-app/constants/theme.ts` (`DESIGN_VARIANTS`). The app is the
 * source of truth; this file exists so the web editor's phone preview renders
 * the exact same shapes instead of hand-tuned approximations.
 *
 * ⚠️ Keep both sides in lock-step. When a preset is added or a number changes
 * in the app's `constants/theme.ts`, mirror it here in the same commit. Adding
 * a new preset is then a one-line data change on both sides — every preview
 * screen follows automatically (see `previewStyles.ts`).
 *
 * A preset changes ONLY the shape of the UI (corner radius, card surface,
 * shadow, density, button shape, tab-bar treatment) — never the palette, font,
 * layout or content. It is orthogonal to the colour system.
 */
export type DesignVariantKey = 'classic' | 'soft-luxury';

/** RN shadow preset. The preview converts this to a scaled CSS box-shadow. */
export interface ShadowPreset {
    shadowColor: string;
    shadowOpacity: number;
    shadowRadius: number;
    shadowOffset: { width: number; height: number };
    elevation: number;
}

export interface DesignVariant {
    key: DesignVariantKey;
    /** Corner radii per surface type. */
    radius: { card: number; tile: number; button: number };
    /** Card / surface treatment. */
    card: {
        surfaceAlpha: number;
        borderWidth: number;
        padding: number;
        shadow: ShadowPreset;
    };
    /** Vertical gap between stacked cards. */
    gap: number;
    /** Tab bar treatment: docked (classic) vs a rounded, elevated "sheet". */
    tabBar: 'docked' | 'sheet';
    /** Radius applied to the floating tab-bar sheet (0 when docked). */
    tabBarRadius: number;
    /** 0 = glass-lite (no blur); > 0 = true frosted blur (app-side opt-in). */
    blur: number;
}

const SHADOW_FLAT: ShadowPreset = {
    shadowColor: '#5a3238',
    shadowOpacity: 0.06,
    shadowRadius: 3,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
};

const SHADOW_SOFT: ShadowPreset = {
    shadowColor: '#5a3238',
    shadowOpacity: 0.16,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 12 },
    elevation: 8,
};

export const DESIGN_VARIANTS: Record<DesignVariantKey, DesignVariant> = {
    classic: {
        key: 'classic',
        radius: { card: 18, tile: 8, button: 12 },
        card: { surfaceAlpha: 1, borderWidth: 1, padding: 16, shadow: SHADOW_FLAT },
        gap: 16,
        tabBar: 'docked',
        tabBarRadius: 0,
        blur: 0,
    },
    'soft-luxury': {
        key: 'soft-luxury',
        radius: { card: 26, tile: 16, button: 9999 },
        card: { surfaceAlpha: 0.88, borderWidth: 0, padding: 20, shadow: SHADOW_SOFT },
        gap: 18,
        tabBar: 'sheet',
        tabBarRadius: 24,
        blur: 0,
    },
};

/**
 * Static token scale — mirror of the app's `theme` in `constants/theme.ts`.
 * The guest screens resolve CLASSIC surfaces against this static scale (e.g. a
 * classic card is `borderRadius.lg`, a classic button is `borderRadius.md`),
 * and only reach for `variant.*` in soft-luxury. The preview must do the same
 * to be 1:1 — see the resolver helpers in `previewStyles.ts`.
 */
export const THEME_RADIUS = { sm: 4, md: 8, lg: 16, full: 9999 } as const;

/**
 * Semantic state colours — mirror of the app's `theme.colors`. Never overridden
 * by the backend palette, so the preview must hardcode the SAME hex values
 * (RSVP accept/decline buttons + status badges use these).
 */
export const SEMANTIC_COLORS = {
    sage: '#5A7A4A', // success / RSVP accepted
    error: '#BF4020', // decline / destructive
    muted: '#7A6A5A', // neutral badge
} as const;

/** The preset used until the backend says otherwise (or for legacy events). */
export const DEFAULT_DESIGN_VARIANT: DesignVariantKey = 'classic';

/** Resolve a (possibly null / unknown) backend preset string to a variant. */
export function getDesignVariant(key: string | null | undefined): DesignVariant {
    return DESIGN_VARIANTS[(key as DesignVariantKey) ?? DEFAULT_DESIGN_VARIANT] ?? DESIGN_VARIANTS[DEFAULT_DESIGN_VARIANT];
}
