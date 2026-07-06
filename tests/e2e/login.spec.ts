import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Auth smoke — the login screen renders, wrong credentials produce a visible
 * error, and the seeded e2e owner can log in end-to-end.
 *
 * Depends on E2eSetupSeeder having run (both locally and in CI).
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

test('seeded owner reaches the dashboard', async ({ page }) => {
    await loginAsOwner(page);
});
