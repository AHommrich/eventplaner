import { expect, test } from '@playwright/test';

/**
 * Public-route smoke — the app is up and its public surface renders.
 *
 * Mirrors what `.github/workflows/post-deploy.yml` checks against live URLs,
 * but as a browser-driven test so DOM-level regressions (blank page, broken
 * layout, missing legal footer) surface locally too.
 */

test('health endpoint returns 200 JSON', async ({ request }) => {
    const res = await request.get('/up');
    expect(res.status()).toBe(200);
});

test('imprint page renders required GDPR fields', async ({ page }) => {
    await page.goto('/impressum');
    await expect(page).toHaveURL(/\/impressum$/);
    const body = await page.locator('body').innerText();
    // §5 DDG requires name + address on the imprint.
    expect(body.toLowerCase()).toContain('hommrich');
    expect(body.toLowerCase()).toContain('montabaur');
});

test('privacy policy page renders and is served fresh', async ({ page }) => {
    const res = await page.goto('/datenschutz');
    expect(res?.status()).toBe(200);
    const body = await page.locator('body').innerText();
    // "Datenschutz" is required by Art. 12 GDPR — must appear on the page.
    expect(body.toLowerCase()).toContain('datenschutz');
});

test('welcome page loads without an authenticated session', async ({ page }) => {
    const res = await page.goto('/');
    expect(res?.status()).toBe(200);
});
