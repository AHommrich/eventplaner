import { describe, expect, it } from 'vitest';
import {
    contrastRatio,
    meetsAA,
    meetsAAA,
    WCAG_AA_LARGE,
    WCAG_AA_NORMAL,
    WCAG_AAA_NORMAL,
} from '../colorContrast';

describe('contrastRatio', () => {
    it('returns 21 for pure black on white (the theoretical maximum)', () => {
        expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 1);
    });

    it('returns 1 for identical colors', () => {
        expect(contrastRatio('#7c2d3e', '#7c2d3e')).toBe(1);
        expect(contrastRatio('#ffffff', '#ffffff')).toBe(1);
    });

    it('is symmetric — swapping fg/bg does not change the ratio', () => {
        expect(contrastRatio('#7c2d3e', '#ffffff')).toBeCloseTo(contrastRatio('#ffffff', '#7c2d3e'), 5);
    });

    it('accepts 3-char shorthand hex', () => {
        expect(contrastRatio('#000', '#fff')).toBeCloseTo(contrastRatio('#000000', '#ffffff'), 5);
    });

    it('normalises the leading hash', () => {
        expect(contrastRatio('7c2d3e', 'ffffff')).toBeCloseTo(contrastRatio('#7c2d3e', '#ffffff'), 5);
    });

    it('throws on malformed input', () => {
        expect(() => contrastRatio('#zzz', '#ffffff')).toThrow(/Invalid hex/);
        expect(() => contrastRatio('#12345', '#ffffff')).toThrow(/Invalid hex/);
    });
});

describe('meetsAA', () => {
    it('accepts the wedding-default Bordeaux-on-White (real bordeaux #7c2d3e vs white)', () => {
        // #7c2d3e on #ffffff → ratio ≈ 9.8, well above AA-normal (4.5).
        expect(meetsAA('#7c2d3e', '#ffffff')).toBe(true);
    });

    it('rejects Beige-on-White (both are near white — collapses)', () => {
        // #e8e3de on #ffffff → ratio ≈ 1.1, fails AA hard.
        expect(meetsAA('#e8e3de', '#ffffff')).toBe(false);
    });

    it('applies the lower threshold for large text', () => {
        // Grey #999 on white is ~2.85 — fails AA normal, but let's use
        // a color that lands between 3.0 and 4.5 to check the large-text switch.
        // #777 on white ≈ 4.48 — technically just below AA normal.
        expect(meetsAA('#777', '#fff', false)).toBe(false);
        expect(meetsAA('#777', '#fff', true)).toBe(true);
    });

    it('boundary constants are exactly the WCAG numbers', () => {
        expect(WCAG_AA_NORMAL).toBe(4.5);
        expect(WCAG_AA_LARGE).toBe(3.0);
        expect(WCAG_AAA_NORMAL).toBe(7.0);
    });
});

describe('meetsAAA', () => {
    it('bordeaux-on-white passes AAA (ratio ≈ 9.8)', () => {
        expect(meetsAAA('#7c2d3e', '#ffffff')).toBe(true);
    });

    it('a mid-grey that passes AA-normal but not AAA', () => {
        // #666 on white ≈ 5.7 — above AA normal (4.5), below AAA (7.0).
        expect(meetsAA('#666', '#fff')).toBe(true);
        expect(meetsAAA('#666', '#fff')).toBe(false);
    });
});
