import { expect, test } from '@playwright/test';

/**
 * Auth smoke — the login screen renders, wrong credentials produce a visible
 * error, and (when the demo seeder has been run) valid credentials land on
 * the dashboard.
 *
 * Uses only the built-in Laravel login endpoint — no direct DB access — so it
 * is safe to run against any environment where DemoDataSeeder has executed.
 */

test('login page renders the form', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();
});

test('wrong password stays on the login page and shows an error', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[type="email"], input[name="email"]').first().fill('nobody@example.invalid');
    await page.locator('input[type="password"], input[name="password"]').first().fill('definitely-wrong');
    await page.locator('button[type="submit"]').first().click();

    // Laravel returns to /login with a validation error — URL stays on login,
    // and the page contains an error string in either German or English.
    await expect(page).toHaveURL(/\/login/);
    const body = await page.locator('body').innerText();
    expect(body).toMatch(/credentials|passwort|password|anmelde/i);
});

test.describe('with the demo seeder', () => {
    // Skip when the demo user is not present (env-gated so CI stays green
    // even when the seeder hasn't been run yet).
    test.skip(!process.env.E2E_DEMO_USER_READY, 'set E2E_DEMO_USER_READY=1 after `db:seed --class=DemoDataSeeder`');

    test('demo credentials reach the dashboard', async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[name="email"]').fill('demo@eveplan.app');
        await page.locator('input[name="password"]').fill('demo-1234');
        await page.locator('button[type="submit"]').click();

        await page.waitForURL(/\/dashboard/);
        await expect(page).toHaveURL(/\/dashboard/);
    });
});
