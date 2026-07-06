import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import * as Sentry from '@sentry/vue';
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

        // Frontend error monitoring — mirrors the backend Sentry config
        // (EU region, no PII, low trace sample). Init only when a DSN is
        // present so local dev + tests never phone home. See
        // docs/legal/sub-processors.md for the DPA reference.
        //
        // `environment` is read from an explicit VITE_SENTRY_ENVIRONMENT
        // build-arg (`staging` / `production` — set per Coolify application)
        // because import.meta.env.MODE is always "production" for any
        // `npm run build`, regardless of which env we're deploying to.
        const sentryDsn = import.meta.env.VITE_SENTRY_DSN;
        if (sentryDsn) {
            Sentry.init({
                app: vue,
                dsn: sentryDsn,
                environment: import.meta.env.VITE_SENTRY_ENVIRONMENT ?? import.meta.env.MODE,
                tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0.05),
                sendDefaultPii: false,
            });
        }

        vue.mount(el);
    },
    progress: { color: '#4B5563' },
});

// Set theme (light/dark)
initializeTheme();
