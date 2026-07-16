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

interface Hsl {
    h: number;
    s: number;
    l: number;
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

function rgbToHsl({ r, g, b }: Rgb): Hsl {
    const red = r / 255;
    const green = g / 255;
    const blue = b / 255;
    const max = Math.max(red, green, blue);
    const min = Math.min(red, green, blue);
    const delta = max - min;
    const l = (max + min) / 2;

    if (delta === 0) return { h: 0, s: 0, l };

    const s = delta / (1 - Math.abs(2 * l - 1));
    let h = 0;
    if (max === red) h = ((green - blue) / delta) % 6;
    else if (max === green) h = (blue - red) / delta + 2;
    else h = (red - green) / delta + 4;

    return { h: (h * 60 + 360) % 360, s, l };
}

function hslToHex({ h, s, l }: Hsl): string {
    const chroma = (1 - Math.abs(2 * l - 1)) * s;
    const x = chroma * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = l - chroma / 2;
    const [red, green, blue] =
        h < 60
            ? [chroma, x, 0]
            : h < 120
              ? [x, chroma, 0]
              : h < 180
                ? [0, chroma, x]
                : h < 240
                  ? [0, x, chroma]
                  : h < 300
                    ? [x, 0, chroma]
                    : [chroma, 0, x];
    const channel = (value: number) =>
        Math.round((value + m) * 255)
            .toString(16)
            .padStart(2, '0');

    return `#${channel(red)}${channel(green)}${channel(blue)}`.toUpperCase();
}

function relativeLuminance({ r, g, b }: Rgb): number {
    const channel = (v: number): number => {
        const s = v / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** Relative luminance in [0, 1], useful for choosing surface colours. */
export function colorLuminance(color: string): number {
    return relativeLuminance(parseHex(color));
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

/**
 * Finds the nearest usable brightness variant of `color` against `against`.
 * Hue and saturation are deliberately kept intact: the designer may decide
 * which of the shared palette colours is allowed to change, but never loses
 * the character of that colour to an automatic black/white replacement.
 */
export function findBrightnessContrastCandidate(color: string, against: string, minimumRatio: number): string | null {
    const source = rgbToHsl(parseHex(color));
    let candidate: { hex: string; distance: number } | null = null;

    for (let lightness = 0; lightness <= 100; lightness += 0.25) {
        const hex = hslToHex({ ...source, l: lightness / 100 });
        if (contrastRatio(hex, against) < minimumRatio) continue;

        const distance = Math.abs(lightness / 100 - source.l);
        if (!candidate || distance < candidate.distance) candidate = { hex, distance };
    }

    return candidate?.hex ?? null;
}
