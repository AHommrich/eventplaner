import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('i18n plugin', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.resetModules();
    });

    it('defaults to German (de) when no locale is stored', async () => {
        const { i18n } = await import('../i18n');
        expect(i18n.global.locale.value).toBe('de');
        expect(i18n.global.fallbackLocale.value).toBe('de');
    });

    it('uses the locale stored in localStorage on load', async () => {
        localStorage.setItem('locale', 'en');
        const { i18n } = await import('../i18n');
        expect(i18n.global.locale.value).toBe('en');
    });

    it('persists the new locale to localStorage when setLocale is called', async () => {
        const { i18n, setLocale } = await import('../i18n');

        setLocale('en');
        expect(localStorage.getItem('locale')).toBe('en');
        expect(i18n.global.locale.value).toBe('en');

        setLocale('de');
        expect(localStorage.getItem('locale')).toBe('de');
        expect(i18n.global.locale.value).toBe('de');
    });
});
