import { contrastRatio, WCAG_AA_NORMAL } from './colorContrast';

export interface DesignColorWorld {
    id: 'bordeaux-ivory' | 'sage-linen' | 'midnight-champagne';
    primary: string;
    secondary: string;
    tertiary: string;
    roles: Record<DesignRole, PaletteSlot>;
}

export type PaletteSlot = 'primary' | 'secondary' | 'tertiary';
export type DesignRole =
    | 'role_screen_bg'
    | 'role_card_bg'
    | 'role_card_text'
    | 'role_card_button'
    | 'role_card_button_text'
    | 'role_tab_tint'
    | 'role_border'
    | 'role_fab'
    | 'role_fab_icon'
    | 'role_nav_bg';

const readableDefaultRoles: Record<DesignRole, PaletteSlot> = {
    role_screen_bg: 'secondary',
    role_card_bg: 'tertiary',
    role_card_text: 'primary',
    role_card_button: 'primary',
    role_card_button_text: 'tertiary',
    role_tab_tint: 'primary',
    role_border: 'primary',
    role_fab: 'primary',
    role_fab_icon: 'tertiary',
    role_nav_bg: 'tertiary',
};

// Code-backed starting points are intentionally global: unlike saved styles,
// they are available to every event and never depend on event-owned records.
export const designColorWorlds: DesignColorWorld[] = [
    { id: 'bordeaux-ivory', primary: '#7C2D3E', secondary: '#F3EDE7', tertiary: '#FFFFFF', roles: readableDefaultRoles },
    { id: 'sage-linen', primary: '#355E52', secondary: '#EEE9DF', tertiary: '#FFFFFF', roles: readableDefaultRoles },
    { id: 'midnight-champagne', primary: '#24324A', secondary: '#EEE3CF', tertiary: '#FFFFFF', roles: readableDefaultRoles },
];

export function hasAccessibleDefaultContrast(world: DesignColorWorld): boolean {
    return (
        world.roles.role_card_text === 'primary' &&
        world.roles.role_card_bg === 'tertiary' &&
        world.roles.role_card_button_text === 'tertiary' &&
        world.roles.role_card_button === 'primary' &&
        contrastRatio(world.primary, world.tertiary) >= WCAG_AA_NORMAL &&
        contrastRatio(world.primary, world.secondary) >= WCAG_AA_NORMAL
    );
}
