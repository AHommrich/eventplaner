import { colorLuminance, contrastRatio, WCAG_AA_NORMAL } from '@/lib/colorContrast';
import type { PaletteKey } from '@/lib/colorResolver';

export type DesignRoleKey = 'screenBg' | 'cardBg' | 'cardText' | 'cardButton' | 'cardButtonText' | 'navBg' | 'tabTint' | 'border' | 'fab' | 'fabIcon';

export type DesignRoleRecommendation = Record<DesignRoleKey, PaletteKey>;

const paletteKeys: PaletteKey[] = ['primary', 'secondary', 'tertiary'];
const MIN_SURFACE_LUMINANCE = 0.35;

function mostContrasting(palette: Record<PaletteKey, string>, against: PaletteKey): PaletteKey {
    return paletteKeys.reduce((best, candidate) =>
        contrastRatio(palette[candidate], palette[against]) > contrastRatio(palette[best], palette[against]) ? candidate : best,
    );
}

/**
 * Derives a conservative semantic role mapping from any three-colour palette.
 * A dark or saturated colour never becomes a large app surface merely because
 * it happens to occupy the secondary palette slot. If the palette contains
 * only one usable light surface, screen and cards intentionally share it.
 */
export function recommendDesignRoles(palette: Record<PaletteKey, string>): DesignRoleRecommendation {
    const lightestFirst = [...paletteKeys].sort((left, right) => colorLuminance(palette[right]) - colorLuminance(palette[left]));
    const cardBg = lightestFirst[0];
    const screenBg = lightestFirst.slice(1).find((key) => colorLuminance(palette[key]) >= MIN_SURFACE_LUMINANCE) ?? cardBg;
    const cardText = mostContrasting(palette, cardBg);
    const accent =
        [...paletteKeys]
            .sort((left, right) => colorLuminance(palette[left]) - colorLuminance(palette[right]))
            .find((key) => contrastRatio(palette[mostContrasting(palette, key)], palette[key]) >= WCAG_AA_NORMAL) ?? cardText;
    const buttonText = mostContrasting(palette, accent);

    return {
        screenBg,
        cardBg,
        cardText,
        cardButton: accent,
        cardButtonText: buttonText,
        navBg: cardBg,
        tabTint: cardText,
        border: accent,
        fab: accent,
        fabIcon: buttonText,
    };
}
