# Eventplaner

[![Tests](https://github.com/AHommrich/eventplaner/actions/workflows/tests.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/tests.yml)
[![Lint](https://github.com/AHommrich/eventplaner/actions/workflows/lint.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/lint.yml)
[![E2E](https://github.com/AHommrich/eventplaner/actions/workflows/e2e.yml/badge.svg?branch=develop)](https://github.com/AHommrich/eventplaner/actions/workflows/e2e.yml)
[![License](https://img.shields.io/badge/License-All%20Rights%20Reserved-red.svg)](LICENSE)

Wedding and event planner built as a Progressive Web App with a React Native companion: tiered organizer collaboration, guest management, RSVPs, notes and ToDos, photo workflows, party games, optional task pushes, and a token-protected projector slideshow.

> Auch auf Deutsch verfügbar: [README.de.md](README.de.md)

![Event settings with live phone preview](docs/screenshots/event-settings.png)

![Photo gallery with guest attribution](docs/screenshots/photos.png)

![Projector slideshow in fullscreen](docs/screenshots/projector.png)

---

## Feature highlights

Sorted from "technically interesting" to "UX polish". Each item links to the central file that implements the mechanism.

1. **QR login for guests** — Sanctum bearer tokens via QR code. Solo guests receive the token directly; family groups first pick a member so unused tokens don't block other family members.
   → [`app/Http/Controllers/Api/QrAuthController.php`](app/Http/Controllers/Api/QrAuthController.php)

2. **Tiered per-event authorization** — Owner, Event Admin and Event Manager are separate event roles with one authoritative policy/service layer. Owners can delegate operational work without exposing deep settings, access administration or ownership controls.
   → [`app/Policies/EventPolicy.php`](app/Policies/EventPolicy.php), [`app/Services/EventAccessService.php`](app/Services/EventAccessService.php)

3. **Isolated mobile management API** — approved, verified organizers pair the app separately from guests through a short-lived, one-time QR generated in the authenticated web account. Every event-scoped request is re-authorized from `X-Event-ID`; Notes/ToDos, cross-gallery photo deletion and optional Expo pushes use this namespace. The backend password contract remains available, but the native client intentionally defers direct account login until OAuth can ship alongside it.
   → [`routes/api.php`](routes/api.php), [`app/Http/Middleware/ResolveManagementEvent.php`](app/Http/Middleware/ResolveManagementEvent.php)

4. **Photo game with a delta-override model** — global task catalogs (general + event-type specific) plus per-event overrides (`hidden` / `modified` / `added`). The standard tasks stay maintainable in a single place; events only store deltas.
   → [`app/Http/Controllers/Api/PhotoGameController.php`](app/Http/Controllers/Api/PhotoGameController.php)

5. **Drinking-game scoring with physiologically motivated multipliers** — base formula `liters × % × 10`, shot multiplier 2.0 for spirits (faster absorption), 50% binge penalty after three alcoholic drinks in a row, negative points for water and soft drinks.
   → [`app/Services/DrinkScoreService.php`](app/Services/DrinkScoreService.php)

6. **Configurable color system with live preview** — three palette slots (primary/secondary/tertiary) and nine role fields that store keys rather than hex values. Change the palette and every role follows. Four simulated app screens react live in the settings split-screen.
   → [`resources/js/pages/Event/Settings.vue`](resources/js/pages/Event/Settings.vue)

7. **Clean drink-catalog modeling** — one row per drink type in `drink_catalog`, one row per size in `drink_catalog_sizes`, the per-event selection in `drinks` references both. `drink_logs` keep `amount_liter` denormalized so historic points stay stable.
   → [`app/Models/DrinkCatalog.php`](app/Models/DrinkCatalog.php), [`database/migrations/2026_03_29_000001_refactor_drink_catalog_to_type_only.php`](database/migrations/2026_03_29_000001_refactor_drink_catalog_to_type_only.php)

8. **Projector slideshow** — public route gated by `projector_token`, auto-polls every 10 s, 5 s crossfade. Context label per album: guest name (gallery), description (presentation) or task text (photo game).
   → [`resources/js/pages/Projector/Show.vue`](resources/js/pages/Projector/Show.vue)

9. **Style presets for one-click theme switching** — predefined palettes can be applied to an event without touching individual fields.
   → [`app/Http/Controllers/EventStylePresetController.php`](app/Http/Controllers/EventStylePresetController.php)

10. **Mobile PWA** — installable on iOS/Android, custom icon set, offline support via `vite-plugin-pwa`.
    → [`vite.config.ts`](vite.config.ts)

11. **i18n on the front- and backend** — vue-i18n v11 for the web app, `Accept-Language` middleware for API responses so the React Native app gets localized drink and photo-game texts.
    → [`resources/js/plugins/i18n.ts`](resources/js/plugins/i18n.ts)

12. **Public landing page** — three-step explainer and feature cards for first-time visitors, no auth required.
    → [`resources/js/pages/Welcome.vue`](resources/js/pages/Welcome.vue)

---

## Tech stack

| Layer        | Technology                                                                                |
| ------------ | ----------------------------------------------------------------------------------------- |
| Backend      | Laravel 12 (PHP 8.3) + Inertia.js + Sanctum                                               |
| Web frontend | Vue 3 + TypeScript + Tailwind CSS 4 + Reka UI                                             |
| Mobile       | React Native (Expo) — [separate repository](https://github.com/AHommrich/eventplaner-app) |
| Build        | Vite 6 + `vite-plugin-pwa`                                                                |
| Storage      | Hetzner Object Storage (photos, Nürnberg)                                                 |
| Mail         | Resend                                                                                    |
| Push         | Expo Push Service (optional organizer notifications)                                      |
| Deploy       | Docker + Coolify                                                                          |

---

## Quick start

The project is fully containerized. Migrations run automatically on boot.

```bash
docker compose up -d
```

Promote the initial admin user inside the running container:

```bash
docker exec laravel-app php artisan tinker
# > User::where('email', 'you@example.com')->update(['role' => 'admin'])
```

Vite runs in its own container and serves assets over HMR.

---

## Deploy workflow

Three long-lived branches, each auto-deployed by Coolify on push. Migrations run automatically.

| Branch       | Environment | Domain              | Dockerfile                              |
| ------------ | ----------- | ------------------- | --------------------------------------- |
| `develop`    | local dev   | —                   | `Dockerfile` (artisan serve, port 8080) |
| `staging`    | staging     | `beta.hommrich.app` | `Dockerfile.prod` (nginx + php-fpm)     |
| `production` | live        | `eveplan.de`        | `Dockerfile.prod` (nginx + php-fpm)     |

`docker-compose.yml` is intentionally different per branch (different Dockerfile, different exposed ports). **Never let the develop version overwrite staging or production.** Every merge to `staging` or `production` resets that file to the target-branch version.

Three safety nets back this up:

- **Always merge locally, never via the GitHub UI.** A server-side merge on github.com ignores the `.gitattributes merge=ours` driver _and_ has no direction guard, so the "Create pull request" button on the wrong branch has already once overwritten `develop`'s dev compose with the prod version (PR #4). All promotions run through the terminal snippets below.
- **`merge=ours` driver** — `.gitattributes` marks `docker-compose.yml` (and `Dockerfile`) as `merge=ours` so git keeps the target-branch version on every _local_ merge instead of trying a three-way merge. Enable the driver once per clone:

    ```bash
    git config --local merge.ours.driver true
    ```

- **CI guard** — `.github/workflows/compose-guard.yml` runs on every push to `staging` and `production` and fails the build if the compose file references the dev Dockerfile or `artisan serve`. If a manual merge ever slips the wrong file through, this catches it before Coolify redeploys.

### develop → staging

```bash
git checkout staging
git pull --ff-only origin staging          # abort if anyone else pushed
git merge --no-ff --no-commit develop
git checkout HEAD -- docker-compose.yml    # keep the staging compose file
grep -q 'Dockerfile.prod' docker-compose.yml || { echo "compose drift"; exit 1; }
git commit -m "Merge branch 'develop' into staging"
git push origin staging
git checkout develop
```

Coolify picks up the push, rebuilds and redeploys. Verify at `https://beta.hommrich.app` before promoting further.

### develop → production

Only promote once staging is green.

```bash
git checkout production
git pull --ff-only origin production       # abort if anyone else pushed
git merge --no-ff --no-commit develop
git checkout HEAD -- docker-compose.yml    # keep the production compose file
grep -q 'Dockerfile.prod' docker-compose.yml || { echo "compose drift"; exit 1; }
git commit -m "Merge branch 'develop' into production"
git push origin production
git checkout develop
```

> ⚠️ **Never push staging and production at the same time.** Two parallel Coolify redeploys exhaust the VPS RAM. Push `staging` first, wait until `beta.hommrich.app` responds, then push `production`.

---

## Architecture (short version)

The data model is centered on **Event as root**: guests, groups, notes, photos, drinks and photo-game tasks all hang off an event. Web organizers authenticate through a Sanctum session; the mobile management surface uses a separate User bearer minted through one-time pairing QR. Guests keep their intentionally narrower QR bearer. Web requests resolve the active event from the session, while management API requests send `X-Event-ID` and re-check account state, token ability, membership and policy tier every time.

Focus subsystems:

- **Photo game** — delta model on top of global task catalogs
- **Authorization** — Owner / Event Admin / Event Manager tiers with last-owner protection
- **Organizer workflow** — private notes, assigned ToDos, device pairing and optional generic pushes
- **Drinking game** — scoring service with shot multiplier and binge penalty
- **Projector** — token-protected slideshow with context-aware labels
- **Color system** — palette + role mapping with live preview

A detailed architecture description, including an ER diagram, auth layers, and subsystem internals, lives in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Tests

| Stack    | Command                                 | What runs                                                            |
| -------- | --------------------------------------- | -------------------------------------------------------------------- |
| Backend  | `composer test`                         | Pest against a dedicated `laravel_test` database with a safety guard |
| Backend  | `./vendor/bin/pest --filter=DrinkScore` | A single test file                                                   |
| Frontend | `npm test`                              | Vitest, parallel worker threads                                      |
| Frontend | `npm run test:watch`                    | Hot-reload tests                                                     |
| Coverage | `./vendor/bin/pest --coverage`          | Backend coverage (Clover XML + text report); Vitest via `--coverage` |

**Coverage:** backend and frontend specs cover API endpoints, policies, cross-event guards,
controllers, retention, services, shared components, page behavior and i18n. The current hardening
working tree is locally verified with **446 passing backend cases (1 skipped)** plus green Pint,
Prettier, ESLint and Vue TypeScript checks.

Test isolation is enforced at the database level: a `TestCase` guard rejects any run that is not connected to the dedicated `laravel_test` database.

---

## Companion app (React Native)

The mobile app lives at [**github.com/AHommrich/eventplaner-app**](https://github.com/AHommrich/eventplaner-app) and shares only the HTTP API with the web app. Guest mode covers QR login, RSVP, schedule, photos, games and privacy self-service. Organizer mode uses an isolated management session, lets an authorized user switch between accessible events, manage Notes/ToDos and delete photos across galleries, and can receive privacy-minimized assignment pushes. The mobile repo documents its client-side boundaries in [`docs/ARCHITECTURE.md`](https://github.com/AHommrich/eventplaner-app/blob/main/docs/ARCHITECTURE.md).

---

## GDPR / data protection

The project is operated from Germany and is documented to be GDPR-ready:

- Imprint at `/impressum` (§5 DDG)
- Privacy policy at `/datenschutz` (Art. 13 GDPR), with mandatory signup consent
- Data export endpoint for the right of access (Art. 15)
- Cascade deletion of photos in Hetzner Object Storage on account/event removal (Art. 17)
- Scheduled retention cleanup for expired invitation tokens and declined guests (Art. 5)
- Privacy-minimized Expo pushes: generic lock-screen copy, opt-out, and receipt-driven invalid-token removal
- Sub-processor register in [`docs/legal/sub-processors.md`](docs/legal/sub-processors.md)

The full plan, including stage breakdowns, is in [`docs/GDPR_COMPLIANCE_PLAN.md`](docs/GDPR_COMPLIANCE_PLAN.md).

---

## Documentation

Beyond this README the repo carries a small set of docs, each with a clear purpose:

- [`docs/GETTING_STARTED.md`](docs/GETTING_STARTED.md) — bring the project up locally, including the common pitfalls
- [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) — branch model, commit convention, doc-sync rule
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — subsystem internals (photo game, drink score, projector, color system)
- [`SECURITY.md`](SECURITY.md) — vulnerability disclosure

---

## License

All rights reserved. See [LICENSE](LICENSE). Publicly viewable for portfolio purposes; no reuse, fork, or redistribution without written permission.
