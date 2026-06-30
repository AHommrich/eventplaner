import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/frontend/setup.ts'],
        include: ['resources/js/**/__tests__/*.spec.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: [
                'resources/js/**/*.d.ts',
                'resources/js/types/**',
                'resources/js/**/__tests__/**',
                'resources/js/ssr.ts',
                'resources/js/app.ts',
            ],
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});
