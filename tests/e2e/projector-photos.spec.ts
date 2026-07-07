import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PROJECTOR_TOKEN, SOLO_TOKEN } from './support/fixtures';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Projector-Journey — der End-to-End-Fall der zählt: ein Gast lädt ein Foto
 * hoch, öffnet die Diashow-Route und sieht sein Foto im DOM.
 *
 * Der Projector zeigt das App-Galerie-Album (via projector_album_id im
 * Seeder gesetzt). Ohne Upload würde das Placeholder-Panel „Noch keine
 * Fotos vorhanden" gerendert — der Test unterscheidet beide Fälle.
 */

const TINY_JPEG = readFileSync(join(__dirname, 'fixtures/tiny.jpg'));

test('projector displays a photo after a guest uploads one', async ({ request, page }) => {
    const loginRes = await request.get(`/api/auth/qr/${SOLO_TOKEN}`);
    const login = await loginRes.json();
    const bearer = login.guests[0].token as string;

    const upload = await request.post('/api/photos', {
        headers: { Authorization: `Bearer ${bearer}` },
        multipart: {
            photo: {
                name: 'projector.jpg',
                mimeType: 'image/jpeg',
                buffer: TINY_JPEG,
            },
        },
    });
    expect(upload.status()).toBe(201);

    await page.goto(`/projector/${PROJECTOR_TOKEN}`);
    await expect(page.locator('img').first()).toBeVisible({ timeout: 10_000 });

    // Placeholder darf nicht mehr sichtbar sein sobald ein Foto geladen ist.
    await expect(page.getByText('Noch keine Fotos vorhanden')).toHaveCount(0);

    await request.delete('/api/auth/logout', { headers: { Authorization: `Bearer ${bearer}` } });
});
