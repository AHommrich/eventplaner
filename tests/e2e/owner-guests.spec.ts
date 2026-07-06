import { expect, test } from '@playwright/test';
import { loginAsOwner } from './support/auth';

/**
 * Owner-Journey — legt einen neuen Gast über die UI an und prüft, dass er
 * in der Tabelle sichtbar ist. Deckt den kompletten Inertia-Roundtrip ab:
 * Modal öffnen → Form-Submit → DB-Persist → Tabellen-Refresh.
 *
 * Test-Owner + Event kommen aus E2eSetupSeeder.
 */

test.describe('owner guest management', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsOwner(page);
    });

    test('adds a new guest and shows it in the table', async ({ page }) => {
        const firstname = `Testgast${Date.now()}`;

        await page.goto('/guests');
        await page.getByRole('button', { name: 'Gast hinzufügen' }).click();

        await page.getByPlaceholder('Vorname').fill(firstname);
        await page.getByPlaceholder('Nachname').fill('Testnachname');

        await page.getByRole('button', { name: 'Gast erstellen' }).click();

        // GuestTable zeigt Gruppen collapsed by default — Suche filtert und
        // expandiert die passende Gruppe automatisch. Damit prüfen wir den
        // Roundtrip stabil, unabhängig vom collapse-Zustand.
        await page.getByPlaceholder('Gast suchen…').fill(firstname);
        await expect(page.getByText(firstname, { exact: false })).toBeVisible();
    });
});
