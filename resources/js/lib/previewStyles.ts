/**
 * Preview style helpers — the web editor's counterpart to the guest app's
 * `eventplaner-app/lib/variantStyles.ts`. Turns a `DesignVariant` (from
 * `designVariants.ts`) plus resolved event colours into ready-to-bind CSS
 * `:style` objects for the phone-preview components.
 *
 * The phone preview is a miniature: full-size app values (radii/shadow/padding
 * in dp) are multiplied by `PREVIEW_SCALE` so the preview reads as the SAME
 * shape at ~1/2 size. This is the single knob — change it here and every
 * preview screen re-scales together, instead of the old per-file magic numbers.
 *
 * ⚠️ These helpers mirror HOW THE REAL SCREENS RESOLVE STYLE, which is not a
 * flat token lookup: the guest screens render CLASSIC surfaces against the
 * static `THEME_RADIUS` scale with a hard hairline border and no shadow, and
 * only reach for `variant.*` + glass/gradients in soft-luxury. Keep this file
 * in step with `app/(tabs)/*.tsx`, not just with the token table.
 */
import type { CSSProperties } from 'vue';
import { THEME_RADIUS, type DesignVariant, type ShadowPreset } from './designVariants';

/**
 * Visual scale of the preview relative to the real screen.
 *
 * NOT the geometric phone scale (a 120px preview vs a ~390pt phone would be
 * ~0.3): preview text is boosted above a pure linear miniature so it stays
 * legible, so radii/shadows are matched to that effective text scale instead.
 * 0.45 is the empirical fit — the one value to nudge if the whole preview
 * should read tighter or looser.
 */
export const PREVIEW_SCALE = 0.45;

/** Scale a full-size dp value to preview pixels. */
export function scaled(value: number): number {
    return Math.round(value * PREVIEW_SCALE * 100) / 100;
}

/** Scale a full-size dp value to a `px` string. */
export function px(value: number): string {
    return `${scaled(value)}px`;
}

/** Whether the active preset is the soft-luxury form language. */
export function isSoft(variant: DesignVariant): boolean {
    return variant.key === 'soft-luxury';
}

/** Two-digit alpha hex (`0..1` → `'00'..'ff'`) to append to a 6-digit colour. */
export function alphaHex(alpha: number): string {
    const clamped = Math.max(0, Math.min(1, alpha));
    return Math.round(clamped * 255)
        .toString(16)
        .padStart(2, '0');
}

/** Apply a preset's surface alpha to a solid hex colour (glass-lite). */
export function withSurfaceAlpha(hex: string, variant: DesignVariant): string {
    return /^#[0-9a-fA-F]{6}$/.test(hex) ? hex + alphaHex(variant.card.surfaceAlpha) : hex;
}

/** Convert an RN `ShadowPreset` to a scaled CSS `box-shadow` string. */
export function previewShadow(shadow: ShadowPreset): string {
    const y = scaled(shadow.shadowOffset.height);
    const blur = scaled(shadow.shadowRadius);
    return `0 ${y}px ${blur}px ${shadow.shadowColor}${alphaHex(shadow.shadowOpacity)}`;
}

/** Corner radius for a pill-aware surface (button/tile): `9999` stays a pill. */
export function radiusPx(value: number): string {
    return value >= 9999 ? '9999px' : px(value);
}

// --- Colour maths (ports of variantStyles.ts, for the soft gradients) ---

function clampByte(n: number): number {
    return Math.max(0, Math.min(255, Math.round(n)));
}
function parseHex(hex: string): [number, number, number] | null {
    const m = /^#?([0-9a-fA-F]{6})$/.exec(hex);
    if (!m) return null;
    const int = parseInt(m[1], 16);
    return [(int >> 16) & 255, (int >> 8) & 255, int & 255];
}
function toHex(r: number, g: number, b: number): string {
    return '#' + [r, g, b].map((c) => clampByte(c).toString(16).padStart(2, '0')).join('');
}
/** Blend toward white by `amt` (0..1). */
function lightenHex(hex: string, amt: number): string {
    const rgb = parseHex(hex);
    if (!rgb) return hex;
    return toHex(rgb[0] + (255 - rgb[0]) * amt, rgb[1] + (255 - rgb[1]) * amt, rgb[2] + (255 - rgb[2]) * amt);
}
/** Blend toward black by `amt` (0..1). */
function darkenHex(hex: string, amt: number): string {
    const rgb = parseHex(hex);
    if (!rgb) return hex;
    return toHex(rgb[0] * (1 - amt), rgb[1] * (1 - amt), rgb[2] * (1 - amt));
}
/** Blend two colours: `amt` 0 = a, 1 = b. */
function mixHex(a: string, b: string, amt: number): string {
    const ca = parseHex(a);
    const cb = parseHex(b);
    if (!ca || !cb) return a;
    return toHex(ca[0] + (cb[0] - ca[0]) * amt, ca[1] + (cb[1] - ca[1]) * amt, ca[2] + (cb[2] - ca[2]) * amt);
}

/**
 * Soft-luxury full-screen background gradient — CSS mirror of the app's
 * `screenGradient` + `ScreenGradient` (diagonal, brighter top, a whisper of the
 * brand tone at the bottom). Returns `null` for classic (flat `screenBg`).
 */
export function previewScreenGradient(variant: DesignVariant, screenBg: string, primary: string): string | null {
    if (!isSoft(variant)) return null;
    const top = lightenHex(screenBg, 0.08);
    const bottom = mixHex(screenBg, primary, 0.04);
    return `linear-gradient(160deg, ${top} 0%, ${screenBg} 55%, ${bottom} 100%)`;
}

/**
 * Soft-luxury glossy button/disc fill — CSS mirror of `sheenGradient` (top a
 * touch lighter, bottom a touch darker than the single base colour).
 */
export function previewSheen(hex: string): string {
    return `linear-gradient(to bottom, ${lightenHex(hex, 0.16)}, ${darkenHex(hex, 0.08)})`;
}

// --- Resolved surfaces (mirror how the screens actually compose) ---

/**
 * The list card used on RSVP + Settings. NOT a flat token lookup:
 *   - classic ...... `THEME_RADIUS.lg`, a hard hairline border, NO shadow.
 *   - soft-luxury .. `radius.card`, glass-lite fill, no border, soft shadow.
 * `padding: false` for composite cards that pad their own rows/dividers.
 */
export function previewCardStyle(variant: DesignVariant, cardColor: string, borderColor: string, opts: { padding?: boolean } = {}): CSSProperties {
    const pad = opts.padding === false ? {} : { padding: px(variant.card.padding) };
    if (isSoft(variant)) {
        return {
            backgroundColor: withSurfaceAlpha(cardColor, variant),
            borderRadius: px(variant.radius.card),
            border: 'none',
            boxShadow: previewShadow(variant.card.shadow),
            ...pad,
        };
    }
    return {
        backgroundColor: cardColor,
        borderRadius: px(THEME_RADIUS.lg),
        // App uses a 2px border; scaled that is sub-pixel, so a crisp 1px hairline.
        border: `1px solid ${borderColor}33`,
        boxShadow: 'none',
        ...pad,
    };
}

/**
 * Corner radius for buttons + segmented controls, resolved like the screens:
 * soft-luxury → pill (`radius.button`), classic → `THEME_RADIUS.md`.
 */
export function previewButtonRadius(variant: DesignVariant): string {
    return isSoft(variant) ? radiusPx(variant.radius.button) : px(THEME_RADIUS.md);
}

/**
 * Gallery-tile corner radius: soft-luxury → `radius.tile` (rounded), classic →
 * a near-square 2dp (the app hardcodes `2`, not the token, for classic tiles).
 */
export function previewTileRadius(variant: DesignVariant): string {
    return isSoft(variant) ? radiusPx(variant.radius.tile) : px(2);
}
