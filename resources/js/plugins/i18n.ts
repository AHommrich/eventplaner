import { createI18n } from 'vue-i18n';
import de from '@/locales/de.json';
import en from '@/locales/en.json';

const saved = localStorage.getItem('locale') ?? 'de';

export const i18n = createI18n({
    legacy: false,
    locale: saved,
    fallbackLocale: 'de',
    messages: { de, en },
});

export function setLocale(lang: 'de' | 'en') {
    i18n.global.locale.value = lang;
    localStorage.setItem('locale', lang);
}
