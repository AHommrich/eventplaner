import { expect, test } from '@playwright/test';
import { PROJECTOR_TOKEN } from './support/fixtures';

/**
 * Projector smoke — the token-gated projector page renders for the seeded
 * projector token. Photo rendering itself is covered by projector-photos.spec.ts.
 */

test('renders the slideshow container for a valid token', async ({ page }) => {
    const res = await page.goto(`/projector/${PROJECTOR_TOKEN}`);
    expect(res?.status()).toBe(200);
    await expect(page).toHaveURL(new RegExp(`/projector/${PROJECTOR_TOKEN}$`));
});

test('invalid token returns 4xx', async ({ page }) => {
    const res = await page.goto('/projector/definitely-not-a-real-token');
    expect(res?.status()).toBeGreaterThanOrEqual(400);
});
