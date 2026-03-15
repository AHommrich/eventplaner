import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import os from 'os'

// Lokale IP für Docker/HMR ermitteln
function getLocalIp() {
  const interfaces = os.networkInterfaces()
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name] || []) {
      if (iface.family === 'IPv4' && !iface.internal) return iface.address
    }
  }
  return 'localhost'
}
const localIp = getLocalIp()

export default defineConfig({
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: true,
    origin: `http://${localIp}:5173`,
    hmr: { protocol: 'ws', host: localIp, port: 5173 },
  },

  plugins: [
    laravel({
      input: ['resources/js/app.ts'],
      ssr: 'resources/js/ssr.ts',
      refresh: true,
    }),

    tailwindcss(),

    vue({
      template: {
        transformAssetUrls: { base: null, includeAbsolute: false },
      },
    }),

    // PWA
    VitePWA({
      registerType: 'autoUpdate',
      // Assets, die nicht durch Vite laufen (liegen in public/)
      includeAssets: [
        'favicon.svg',
        'icons/icon-192.png',
        'icons/icon-512.png',
        'icons/maskable-512.png',
      ],
      manifest: {
        name: 'Planer',
        short_name: 'App',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#ffffff',
        theme_color: '#111827',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: '/icons/maskable-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,svg,png,woff2}'],
        runtimeCaching: [
          // HTML/SSR-Dokumente: niemals cachen (CSRF-Token ist session-spezifisch)
          {
            urlPattern: ({ request }) => request.destination === 'document',
            handler: 'NetworkOnly',
          },
          // Bundles/Assets
          {
            urlPattern: ({ request }) =>
              ['style', 'script', 'worker'].includes(request.destination),
            handler: 'StaleWhileRevalidate',
            options: { cacheName: 'assets' },
          },
          // Bilder
          {
            urlPattern: ({ request }) => request.destination === 'image',
            handler: 'CacheFirst',
            options: {
              cacheName: 'images',
              expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 30 },
            },
          },
        ],
      },
        devOptions: { enabled: false },

    }),
  ],
})
