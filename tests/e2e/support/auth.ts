import { expect, type Page } from '@playwright/test';
import { OWNER_EMAIL, OWNER_PASSWORD } from './fixtures';

/**
 * Logs in the seeded e2e owner via the real Laravel login form.
 * Leaves the page on /dashboard.
 *
 * On failure the helper captures the login-POST response + the resulting
 * page state so the assertion error carries usable diagnostic info instead
 * of just "waitForURL timed out".
 */
export async function loginAsOwner(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(OWNER_EMAIL);
    await page.locator('input[type="password"]').fill(OWNER_PASSWORD);

    const [loginResponse] = await Promise.all([
        page.waitForResponse((res) => res.url().includes('/login') && res.request().method() === 'POST'),
        page.locator('button[type="submit"]').click(),
    ]);

    const loginStatus = loginResponse.status();
    const inertiaLocation = loginResponse.headers()['x-inertia-location'];

    try {
        await page.waitForURL(/\/dashboard/, { waitUntil: 'commit', timeout: 15_000 });
    } catch (e) {
        const url = page.url();
        const bodyText = (await page.locator('body').innerText().catch(() => '(no body)')).slice(0, 400);
        const responseBody = (await loginResponse.text().catch(() => '(no body)')).slice(0, 400);
        throw new Error(
            `Login did not reach /dashboard.\n` +
                `  POST /login → status=${loginStatus} X-Inertia-Location=${inertiaLocation ?? '(none)'}\n` +
                `  Response body: ${responseBody}\n` +
                `  Current URL:   ${url}\n` +
                `  Body text:     ${bodyText}`,
        );
    }
    await expect(page).toHaveURL(/\/dashboard/);
}
