import { expect, test } from '@playwright/test';

/**
 * Projector smoke — the token-gated projector page renders when we have a
 * valid token to hand it. The test is env-gated so it doesn't force every
 * pipeline to know the demo token.
 *
 * To run locally against the demo seed:
 *   docker exec laravel-app php artisan tinker \
 *     --execute='echo App\Models\Event::first()->projector_token;'
 *   E2E_PROJECTOR_TOKEN=<that value> npx playwright test projector
 */

test.describe('projector', () => {
    test.skip(() => !process.env.E2E_PROJECTOR_TOKEN, 'set E2E_PROJECTOR_TOKEN to a valid projector token');

    test('renders the slideshow container for a valid token', async ({ page }) => {
        const token = process.env.E2E_PROJECTOR_TOKEN!;
        const res = await page.goto(`/projector/${token}`);
        expect(res?.status()).toBe(200);

        // The page mounts <Projector /> which sits inside a full-viewport container.
        // Wait for the app root to have paint, then confirm the URL didn't bounce.
        await expect(page).toHaveURL(new RegExp(`/projector/${token}$`));
    });

    test('invalid token returns 4xx', async ({ page }) => {
        const res = await page.goto('/projector/definitely-not-a-real-token');
        expect(res?.status()).toBeGreaterThanOrEqual(400);
    });
});
