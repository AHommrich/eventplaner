import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright smoke suite.
 *
 * The tests intentionally cover a *thin* golden path — health, public legal
 * pages, and the auth error path — so they stay stable when UI details drift.
 * Deeper owner/guest flows are tracked as a follow-up in
 * docs/AUDIT_YELLOW_TO_GREEN.md.
 *
 * Local run:
 *   docker compose up -d
 *   npx playwright install chromium  # first time only
 *   E2E_BASE_URL=http://localhost:8080 npx playwright test
 *
 * CI run: see .github/workflows/e2e.yml — starts `php artisan serve` on port
 * 8080 against a MariaDB service, then executes this config.
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
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
