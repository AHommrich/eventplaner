import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { i18n } from './plugins/i18n';

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    import('virtual:pwa-register').then(({ registerSW }) => {
        registerSW({ immediate: true });
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'eveplan';

/** Axios: relative URLs only + XHR header */
// WORKAROUND: Axios must read the same cookie name that the backend sets.
// See VerifyCsrfToken.php – required because staging/prod run on the same parent domain.
const csrfCookieName = import.meta.env.VITE_CSRF_COOKIE_NAME || 'XSRF-TOKEN';
axios.defaults.baseURL = '/';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.xsrfCookieName = csrfCookieName;
axios.defaults.xsrfHeaderName = `X-${csrfCookieName}`;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/** Ziggy: disable absolute links + set URL if available */
declare global {
    interface Window {
        Ziggy?: any;
    }
}
if (typeof window !== 'undefined' && window.Ziggy) {
    window.Ziggy.absolute = false;
    if (import.meta.env.VITE_APP_URL) {
        window.Ziggy.url = import.meta.env.VITE_APP_URL;
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const vue = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            // If @routes is included, pass Ziggy to the plugin:
            .use(ZiggyVue, typeof window !== 'undefined' ? (window as any).Ziggy : undefined);

        vue.mount(el);
    },
    progress: { color: '#4B5563' },
});

// Set theme (light/dark)
initializeTheme();
