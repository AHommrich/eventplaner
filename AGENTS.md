# AGENTS.md

This file exists so that AI coding tools which don't read `CLAUDE.md`
(Codex, OpenCode, etc.) still get the load-bearing rules. `CLAUDE.md`
is the source of truth for project conventions, data model, and
feature docs — read it in full before making changes.

The rules below are the ones that must hold regardless of which tool
or model is driving:

## Hard rules

- **Branch flow is one direction only:** `feature-branch → develop →
  staging → production`. Promotions to `staging`/`production` are
  local `--no-ff` merges + push, never a GitHub UI merge (see
  `docs/CONTRIBUTING.md` "Branch model" for why).
- **Never force-push, rewrite history, or delete `develop`/`staging`/
  `production`.** `.githooks/pre-push` blocks this technically once
  enabled (`git config core.hooksPath .githooks` — do this once per
  clone if it isn't already set).
- **Never deploy or redeploy without being explicitly asked.** Coolify
  redeploys automatically on push — a push to `staging`/`production`
  *is* a deploy. Never redeploy `staging` and `production` in the same
  window (parallel Coolify redeploys OOM-kill the VPS).
- **Never commit or push on behalf of the user** unless explicitly
  asked in that turn. Hand back a commit message instead.
- **Log non-trivial decisions in `docs/DECISIONS.md`** before ending a
  session — this is what survives context/session limits; the diff
  alone does not carry the *why*.
- **GDPR/infrastructure changes** follow the checklist in `CLAUDE.md`
  §"Governance-Regel" — touching a sub-processor or storage provider
  without updating the docs listed there is a compliance gap, not just
  a doc gap.

## Execution environment (non-obvious — read before running anything)

These trip up any tool that runs commands naively. They are load-bearing:

- **Tests run ONLY against `laravel_test`.** A `TestCase::setUp()` guard throws a
  `RuntimeException` against any other DB (the dev DB was wiped once this way).
  Run inside the `laravel-app` container **and pass an APP_KEY** — the tracked
  `.env.testing` has an *empty* `APP_KEY`, so without it you get
  `MissingAppKeyException`:
  ```
  docker exec laravel-app sh -c 'APP_KEY="base64:WbW7jGEyKUY4O+DvQYyOZ8nteSTHdQ4VDYCcpqJwdHs=" ./vendor/bin/pest'
  ```
- **Never run `migrate:fresh`/`migrate` against the dev DB to "check migrations".**
  The Pest suite already runs every migration via `RefreshDatabase` against
  `laravel_test` on each run — that is the migration check.
- **`php artisan` always via `docker exec laravel-app`, never directly on the host.**
- **PHP formatting:** `docker exec laravel-app ./vendor/bin/pint` (`--test` to check only).
- **Frontend checks run in the Node-20 container `eventplaner-vite-1`** (workdir
  `/var/www`; host Node 16 silently breaks prettier/eslint/vue-tsc):
  ```
  docker exec eventplaner-vite-1 sh -c 'npx prettier --check <files> && npx eslint <files> && npx vue-tsc --noEmit'
  ```
  Use `npx prettier --write` to fix. **`npm run build` fails locally** (esbuild
  macOS↔Linux) — known pre-existing; use `vue-tsc --noEmit` as the type check.
- **Docker not running?** `orb start`, then wait for `laravel-app` + `mariadb` +
  `eventplaner-vite-1` to be up (`docker ps`).
- **i18n parity:** every key added to `resources/js/locales/de.json` must also exist
  in `en.json` (and vice versa). Nav/tab arrays must be `computed()` so they react
  to language switches.

## Working style (how to deliver, so results are consistent across tools)

- **Split large work into independently green checkpoints.** Each checkpoint =
  one coherent unit that leaves pint + pest + prettier/eslint/vue-tsc green on its
  own, with its own handed-back commit message.
- **Verify CI green BEFORE handing back a commit message** — run pest (in the
  `laravel-app` container, with APP_KEY) *and*, if Vue/TS changed,
  prettier/eslint/vue-tsc (in `eventplaner-vite-1`). Don't claim done on unrun tests.
- **Ship tests + docs with the feature, not later.** New behaviour needs Pest
  coverage; role/data-model/API changes update `CLAUDE.md` + `docs/ARCHITECTURE.md`
  + a `docs/DECISIONS.md` entry in the same unit.
- **Commit message = English, house style:** `type(scope): summary`, blank line,
  short body lines explaining the *why*. Hand it back; the human commits.
- Server-side authorization is authoritative; frontend role gating is cosmetic.
  Every mutating controller needs a cross-event guard (object's `event_id` must
  equal the active event) — see the §2.4a audit table in the role plan.

## See also

- `docs/SAFETY_LEVER.md` — full explanation of the hook + the manual
  GitHub branch-protection checklist
- `docs/DECISIONS.md` — decisions log
- `docs/CONTRIBUTING.md` — branch model, commit convention, PR flow
- `docs/GETTING_STARTED.md` — local setup
