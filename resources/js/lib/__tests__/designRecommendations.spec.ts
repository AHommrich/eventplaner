import { describe, expect, it } from 'vitest';
import { recommendDesignRoles } from '../designRecommendations';

describe('recommendDesignRoles', () => {
    it('keeps a dark secondary colour out of large app surfaces', () => {
        const roles = recommendDesignRoles({
            primary: '#5C1209',
            secondary: '#595935',
            tertiary: '#C3B1A0',
        });

        expect(roles.screenBg).toBe('tertiary');
        expect(roles.cardBg).toBe('tertiary');
        expect(roles.cardText).toBe('primary');
        expect(roles.navBg).toBe('tertiary');
    });

    it('keeps the global colour-world surface hierarchy intact', () => {
        expect(
            recommendDesignRoles({
                primary: '#7C2D3E',
                secondary: '#F3EDE7',
                tertiary: '#FFFFFF',
            }),
        ).toMatchObject({ screenBg: 'secondary', cardBg: 'tertiary', navBg: 'tertiary', cardText: 'primary' });
    });
});
