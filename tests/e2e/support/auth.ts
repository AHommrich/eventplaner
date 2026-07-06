import { expect, type Page } from '@playwright/test';
import { OWNER_EMAIL, OWNER_PASSWORD } from './fixtures';

/**
 * Logs in the seeded e2e owner via the real Laravel login form.
 * Leaves the page on /dashboard.
 */
export async function loginAsOwner(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(OWNER_EMAIL);
    await page.locator('input[type="password"]').fill(OWNER_PASSWORD);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL(/\/dashboard/);
    await expect(page).toHaveURL(/\/dashboard/);
}
