import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Owner-Journey — ändert die Primärfarbe der Event-Palette über die UI
 * und prüft, dass der Wert nach einem Reload persistiert bleibt.
 * Deckt den Farb-Roundtrip auf der App → Design-Seite ab.
 *
 * Der Test ist selbstheilend: er wählt den nächsten Zielwert relativ zum
 * aktuellen, so dass wiederholte Läufe stabil bleiben.
 */

test.describe('owner app design', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsOwner(page);
    });

    test('persists a primary color change', async ({ page }) => {
        await page.goto('/app/design');

        const colorInput = page.locator('input[type="color"]').first();
        const before = (await colorInput.inputValue()).toLowerCase();
        const next = before === '#abcdef' ? '#112233' : '#abcdef';

        await colorInput.fill(next);

        // Floating Save-Bar erscheint nur wenn form.isDirty === true.
        await page.getByRole('button', { name: 'Speichern', exact: true }).click();
        await page.waitForResponse((res) => res.url().includes('/app/design') && res.request().method() === 'POST' && res.status() < 400);

        await page.reload();
        const after = (await page.locator('input[type="color"]').first().inputValue()).toLowerCase();
        expect(after).toBe(next);
        expect(after).not.toBe(before);
    });
});
