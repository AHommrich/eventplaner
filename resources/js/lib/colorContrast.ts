/**
 * WCAG 2.1 contrast ratio utility for the event color editor.
 *
 * The editor lets the owner pick foreground + background from a three-slot
 * palette. Nothing stops them from choosing white-on-white or bordeaux-on-
 * bordeaux — that would be a WCAG-AA violation for every guest reading the
 * settings preview.
 *
 * `contrastRatio` implements the WCAG 2.1 relative-luminance formula and
 * returns a raw ratio in [1, 21]. `meetsAA` / `meetsAAA` are threshold
 * helpers so callers don't have to remember the magic numbers.
 *
 * Only hex inputs (`#rgb` / `#rrggbb`) are supported — that's what the
 * color-picker in the app emits. Invalid hex throws.
 */

/** WCAG 2.1 AA — minimum contrast for normal-sized body text. */
export const WCAG_AA_NORMAL = 4.5;

/** WCAG 2.1 AA — minimum contrast for large text (>=18pt, or >=14pt bold). */
export const WCAG_AA_LARGE = 3.0;

/** WCAG 2.1 AAA — enhanced contrast for normal-sized body text. */
export const WCAG_AAA_NORMAL = 7.0;

interface Rgb {
    r: number;
    g: number;
    b: number;
}

function parseHex(hex: string): Rgb {
    const stripped = hex.trim().replace(/^#/, '');
    const expanded =
        stripped.length === 3
            ? stripped
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : stripped;

    if (!/^[0-9a-fA-F]{6}$/.test(expanded)) {
        throw new Error(`Invalid hex color: ${hex}`);
    }

    return {
        r: parseInt(expanded.slice(0, 2), 16),
        g: parseInt(expanded.slice(2, 4), 16),
        b: parseInt(expanded.slice(4, 6), 16),
    };
}

function relativeLuminance({ r, g, b }: Rgb): number {
    const channel = (v: number): number => {
        const s = v / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/**
 * WCAG contrast ratio between two colors. Symmetric — swapping the arguments
 * yields the same result. Range: [1, 21].
 */
export function contrastRatio(fg: string, bg: string): number {
    const l1 = relativeLuminance(parseHex(fg));
    const l2 = relativeLuminance(parseHex(bg));
    const [lighter, darker] = l1 >= l2 ? [l1, l2] : [l2, l1];
    return (lighter + 0.05) / (darker + 0.05);
}

export function meetsAA(fg: string, bg: string, largeText = false): boolean {
    return contrastRatio(fg, bg) >= (largeText ? WCAG_AA_LARGE : WCAG_AA_NORMAL);
}

export function meetsAAA(fg: string, bg: string): boolean {
    return contrastRatio(fg, bg) >= WCAG_AAA_NORMAL;
}
