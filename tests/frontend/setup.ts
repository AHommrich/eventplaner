import { config } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { vi } from 'vitest';

const messages = {
    de: {
        confirm: { defaultTitle: 'Bestätigen', cancel: 'Abbrechen', confirm: 'OK' },
        common: { createItem: 'Neu: {name}', createItemAgain: 'Erneut anlegen: {name}?' },
        guest: { groupSearchPlaceholder: 'Suchen…' },
    },
    en: {
        confirm: { defaultTitle: 'Confirm', cancel: 'Cancel', confirm: 'OK' },
        common: { createItem: 'New: {name}', createItemAgain: 'Create again: {name}?' },
        guest: { groupSearchPlaceholder: 'Search…' },
    },
};

const i18n = createI18n({
    legacy: false,
    locale: 'de',
    fallbackLocale: 'de',
    messages,
});

config.global.plugins = [i18n];

// Ziggy `route()` ist global im echten Code — Tests bekommen einen Stub.
(globalThis as unknown as { route: (...args: unknown[]) => string }).route = vi.fn(
    (name?: string) => `/_test/${name ?? ''}`,
);
