# Resume notes for the next session

_Written 2026-06-30 evening — context ran low so we cut cleanly after EXIF stripping._

## What I shipped today (summary across the whole session)

- **Showcase plan** finished — `docs/SHOWCASE_PLAN.md`, all 5 tags green.
- **GDPR plan** finished — `docs/GDPR_COMPLIANCE_PLAN.md`, stages 1–6 done, stage 7 deferred.
- **All three DPAs collected** — Hetzner (signed 2026-06-30), Cloudflare R2 (v6.4), Resend (last updated 2025-12-31). Recorded in `docs/legal/sub-processors.md`.
- **Imprint + privacy policy** live with real address data (Dernbacher Str. 26, 56410 Montabaur, andre-hommrich@web.de).
- **Code & docs translated to English** — 60 PHP files, 16 Vue/TS files, README.md (with README.de.md as a German copy), docs/ARCHITECTURE.md.
- **EXIF stripping** — `App\Services\PhotoSanitizer` rebuilds every uploaded image to JPEG without EXIF/IPTC/XMP. Wired into the four upload paths (Api/PhotoController, PhotoController, Api/PhotoGameController, EventSettingsController cover). Driver: Imagick when available, GD otherwise. Verified by `tests/Feature/Photo/ExifStrippingTest.php`.

## Repo state at cut

- `composer test`: **198 passed, 1 skipped, 449 assertions** ✅
- `npm test` (Vitest): **16 passed** ✅
- `vendor/bin/pint --test`: clean ✅
- `npm run lint`: clean ✅
- `vue-tsc --noEmit`: **12 preexisting errors** (untouched today) ❌

## What's still open — pick up in this order

### 1. `vue-tsc` errors (~2h) — biggest readability blocker
12 errors across:
- `resources/js/pages/Drinks/Game.vue` (2× `Property 'drink_id' does not exist`)
- `resources/js/pages/Event/Settings.vue` (2× `Property 'setTimeout' does not exist`)
- `resources/js/pages/Guests/Edit.vue` (1× `qrcode` module type)
- `resources/js/pages/Invitations/Index.vue` (1× `qrcode` module type)
- `resources/js/pages/Onboarding.vue` (1× `router.back` doesn't exist)
- `resources/js/pages/Photos/Index.vue` (2× `string | null` not assignable to `string | undefined`)
- One event-handler type mismatch (Event vs KeyboardEvent — see vue-tsc output)
- Run `docker exec eventplaner-vite-1 npx vue-tsc --noEmit 2>&1 | tail -40` to see the full list.

### 2. `vue-tsc` step in `.github/workflows/tests.yml` (~5min)
Add `npm run typecheck` (script: `vue-tsc --noEmit`) right next to the existing `npm run build`. Must follow #1 — adding the gate before fixing the errors would brick CI.

### 3. R2 EU jurisdiction migration plan (~30min, docs only)
Write `docs/legal/r2-eu-jurisdiction-migration.md` describing:
- How to create a new R2 bucket pinned to the EU jurisdiction.
- How to copy existing objects across (`rclone` or Cloudflare's R2 Super Slurper).
- Which env vars to switch (`AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_DEFAULT_REGION`).
- Already linked from `docs/legal/sub-processors.md` known-gaps section.

### 4. README.de.md sync (~15min)
The English README got a GDPR section and a few touch-ups after we forked the German copy. Mirror those into README.de.md so the German version isn't half a session behind.

### 5. CLAUDE.md update (~15min)
Add the new bits so the next AI run starts informed:
- Routes `/impressum`, `/datenschutz` + LegalController
- `SecurityHeaders` middleware (HSTS env-gated to prod/staging)
- `PhotoObserver` + `photos:cleanup-orphans` command
- `UserDataExporter` service + `/settings/export-data` route
- Retention commands `app:prune-invitation-tokens`, `app:prune-declined-guests`
- `users.privacy_accepted_at` column + signup-consent checkbox
- `App\Services\PhotoSanitizer` for EXIF stripping (use this for any new upload path!)

### 6. Privacy.vue retention numbers (~5min)
Section "6. Speicherdauer" already mentions the windows in prose. The plan said to fill in the exact numerals from `config/retention.php`. Quick polish — paste the `30` and `180` day defaults explicitly so the policy doesn't drift from `config()`.

### 7. Memory `project_roadmap.md` (~10min)
106+ days old. Replace with the post-Showcase / post-GDPR / post-translation reality.

### 8. Three screenshots for README (only you can do this)
- Web dashboard after login
- React Native app (home or photos screen)
- Projector slideshow in fullscreen with label overlay

Drop them into `docs/screenshots/` and uncomment the three `<!-- TODO -->` blocks in README.md + README.de.md.

### 9. Privacy policy legal review (only you / a lawyer)
The text is solid for a small SaaS, but a real DSGVO-lawyer pass before going public would close the last gap.

### 10. Staging deploy + end-to-end Privacy flow check (only you)
- Sign up with the new consent checkbox.
- Download the data export and open it.
- Delete the account, check that R2 objects are gone.
- Verify HSTS + CSP headers are present in the staging response.

## Working agreements (already in memory, but as a reminder)

- **No git actions by Claude** — André commits + pushes himself.
- **No deploys without explicit ask.**
- **Tests run only against `laravel_test`** via the `mysql_testing` connection.
- **Commit messages in English**, conversation stays German.
- **All docs and code comments in English** (this repo and others) — legal pages and CLAUDE.md may stay German.
- `php artisan` locally via `docker exec laravel-app`.

## Suggested first move next session

```
1. Run docker exec eventplaner-vite-1 npx vue-tsc --noEmit 2>&1 | tail -40
2. Walk down the 12 errors top to bottom.
3. After each batch of fixes: docker exec eventplaner-vite-1 npx vue-tsc --noEmit
4. Once vue-tsc is clean, add `npm run typecheck` to package.json and the CI workflow.
5. Then move on to the docs polishing (#3-#7).
```

Have a good evening — repo is in a clean, mergeable state right now.
