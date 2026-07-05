import { describe, expect, it } from 'vitest';
import { cn } from '../utils';

describe('cn', () => {
    it('joins truthy class values into one string', () => {
        expect(cn('a', 'b', 'c')).toBe('a b c');
    });

    it('skips falsy values', () => {
        expect(cn('a', null, undefined, false, 'b', '')).toBe('a b');
    });

    it('deduplicates Tailwind utilities that target the same style, keeping the last one', () => {
        // tailwind-merge's job: `p-2 p-4` collapses to `p-4`, not both.
        expect(cn('p-2', 'p-4')).toBe('p-4');
        expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500');
    });

    it('handles conditional maps and arrays like clsx does', () => {
        expect(cn(['a', 'b'], { c: true, d: false })).toBe('a b c');
    });
});
