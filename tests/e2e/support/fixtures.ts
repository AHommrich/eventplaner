/**
 * Deterministic e2e fixtures — must stay in sync with
 * database/seeders/E2eSetupSeeder.php.
 *
 * Populate before running the suite:
 *   docker exec laravel-app php artisan db:seed --class=E2eSetupSeeder
 * (CI does this automatically via .github/workflows/e2e.yml.)
 */

export const OWNER_EMAIL = 'e2e@eveplan.test';
export const OWNER_PASSWORD = 'e2e-test-1234';

export const SOLO_TOKEN = 'e2esolotoken00000000000000000000';
export const FAMILY_TOKEN = 'e2efamilytoken000000000000000000';
export const PROJECTOR_TOKEN = 'e2eprojector0000000000000000000a';

export const EVENT_NAME = 'E2E Testfeier';
