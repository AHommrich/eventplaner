import { describe, expect, it } from 'vitest';
import { buildPalette, DEFAULT_PALETTE, resolveRole, type Palette } from '../colorResolver';

describe('buildPalette', () => {
    it('uses the wedding defaults when every slot is empty', () => {
        expect(buildPalette()).toEqual(DEFAULT_PALETTE);
        expect(buildPalette('', '', '')).toEqual(DEFAULT_PALETTE);
        expect(buildPalette(null, null, null)).toEqual(DEFAULT_PALETTE);
        expect(buildPalette(undefined, undefined, undefined)).toEqual(DEFAULT_PALETTE);
    });

    it('honours explicit hex values', () => {
        expect(buildPalette('#000000', '#111111', '#222222')).toEqual({
            primary: '#000000',
            secondary: '#111111',
            tertiary: '#222222',
        });
    });

    it('mixes provided and defaulted slots', () => {
        expect(buildPalette('#123456', null, '')).toEqual({
            primary: '#123456',
            secondary: DEFAULT_PALETTE.secondary,
            tertiary: DEFAULT_PALETTE.tertiary,
        });
    });
});

describe('resolveRole', () => {
    const palette: Palette = { primary: '#111', secondary: '#222', tertiary: '#333' };

    it('resolves an explicit role key to the matching palette hex', () => {
        expect(resolveRole('primary', 'secondary', palette)).toBe('#111');
        expect(resolveRole('secondary', 'primary', palette)).toBe('#222');
        expect(resolveRole('tertiary', 'primary', palette)).toBe('#333');
    });

    it('uses the fallback key when the role is null or undefined', () => {
        expect(resolveRole(null, 'primary', palette)).toBe('#111');
        expect(resolveRole(undefined, 'secondary', palette)).toBe('#222');
    });

    it('falls back when the role points at a missing slot', () => {
        // The palette is intentionally partial — `tertiary` is undefined here.
        const partial = { primary: '#aaa', secondary: '#bbb' } as unknown as Palette;
        expect(resolveRole('tertiary', 'primary', partial)).toBe('#aaa');
    });

    it('follows the role → palette pipeline that the Settings preview relies on', () => {
        // "role_card_text = primary" + palette.primary = "#7c2d3e" (bordeaux) → text renders bordeaux
        const settingsPalette = buildPalette('#7c2d3e', '#e8e3de', '#ffffff');
        expect(resolveRole('primary', 'primary', settingsPalette)).toBe('#7c2d3e');
        expect(resolveRole('secondary', 'primary', settingsPalette)).toBe('#e8e3de');
    });
});
