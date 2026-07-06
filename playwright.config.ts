import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright end-to-end suite.
 *
 * Scope: public smoke (login, imprint, privacy, health) + a11y + owner UI
 * flows (guest CRUD, event settings) + guest API journey (QR-Auth, photo
 * upload) + projector-with-photos rendering.
 *
 * Runs against the seeded E2eSetupSeeder fixture — an owner + event +
 * guests + tokens. Never touches real user data.
 *
 * Local run:
 *   docker compose up -d
 *   docker exec laravel-app php artisan db:seed --class=E2eSetupSeeder
 *   npx playwright install chromium  # first time only
 *   E2E_BASE_URL=http://localhost:8080 npx playwright test
 *
 * CI run: see .github/workflows/e2e.yml — starts `php artisan serve` on
 * port 8080 against a MariaDB service, runs the seeder, then this config.
 */

const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect: { timeout: 5_000 },
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI ? [['github'], ['line']] : 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
