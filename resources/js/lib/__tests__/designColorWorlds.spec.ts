import { describe, expect, it } from 'vitest';
import { designColorWorlds, hasAccessibleDefaultContrast } from '../designColorWorlds';

describe('design color worlds', () => {
    it('provides exactly three globally available, accessible starting points', () => {
        expect(designColorWorlds).toHaveLength(3);
        expect(designColorWorlds.every(hasAccessibleDefaultContrast)).toBe(true);
    });
});
