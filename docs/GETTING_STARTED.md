# Getting Started

Bring the project up on a fresh machine without asking the maintainer.
Written for someone who has never touched this repo before. If any step
here breaks, the fix belongs in this document rather than in a Slack DM.

For the deploy story (branches, Coolify, safety nets) see `README.md`
"Deploy workflow". For high-level architecture see `docs/ARCHITECTURE.md`.

---

## 1. Prerequisites

| Tool                          | Why                                                        |
| ----------------------------- | ---------------------------------------------------------- |
| Docker Desktop or OrbStack    | Runs the app, Vite, and MariaDB containers                 |
| Node.js 22                    | Matches the CI runtime (`.github/workflows/tests.yml`)     |
| `git`                         | Cloning + local merges                                     |

Nothing else needs to be installed on the host — PHP, Composer, and the
database live inside the Docker network.

## 2. Clone and configure

```bash
git clone git@github.com:AHommrich/eventplaner.git
cd eventplaner
cp .env.example .env
git config core.hooksPath .githooks
```

The last line enables the pre-push safety net (blocks force-push /
branch deletion on `develop`/`staging`/`production`, confirms before
any push to `production`). It's a local git config, not something a
clone inherits automatically — see `docs/SAFETY_LEVER.md`.

The default `.env` boots against the bundled MariaDB container, uses a
throwaway `APP_KEY` placeholder, and disables all outbound integrations
(Resend, Sentry, Hetzner Object Storage). Fill in real keys later only
if you want to exercise those code paths — the golden-path features
work without them.

## 3. Start the stack

```bash
docker compose up -d
```

Expected behaviour on the first run (~2 minutes):

- `laravel-app` boots on `http://localhost:8080` — `artisan serve` inside
  the container. Migrations run automatically on boot.
- `eventplaner-vite-1` boots on `http://localhost:5173` — Vite dev
  server with HMR.
- `mariadb` boots on port 3306 with the `laravel` database seeded.

Follow the logs while you wait:

```bash
docker logs -f laravel-app
```

You are ready when `Server running on [http://0.0.0.0:8080]` shows up.

## 4. Promote the initial admin user

Register a normal account at `http://localhost:8080/register`, then flip
its role in the app container:

```bash
docker exec laravel-app php artisan tinker
# > User::where('email', 'you@example.com')->update(['role' => 'admin'])
```

Log out and back in — the sidebar now shows the admin-only sections.

## 5. Two-terminal workflow

Keep two terminals open while working:

| Terminal | Command                     | Purpose                                             |
| -------- | --------------------------- | --------------------------------------------------- |
| 1        | `docker logs -f laravel-app`| PHP errors, Laravel log stream                      |
| 2        | (whatever you're running)   | Tests, one-off `artisan` commands, git              |

The Vue frontend is served through Vite HMR at `localhost:5173` but the
app itself lives at `localhost:8080` — always open the app URL, Vite
just hot-reloads the assets in the background.

## 6. Where to find errors

- `docker logs laravel-app` — request errors, boot warnings
- `storage/logs/laravel.log` — application log (queue jobs, mail, etc.)
- Browser devtools console — Vue warnings, HMR errors
- Sentry (staging + production only, not local dev)

## 7. Common pitfalls

- **Vite HMR stopped picking up file changes.** Docker's file-watch
  event bridge sometimes drops after a while. Restart just the Vite
  container:

  ```bash
  docker restart eventplaner-vite-1
  ```

- **`migrate:fresh` fails with a TLS/SSL error against MariaDB.** The
  local MariaDB container has no TLS certificate. The `laravel-app`
  container ships `/etc/mysql/conf.d/00-no-ssl.cnf` with `skip-ssl`
  to keep the `mysql` CLI happy. If a fresh clone is missing that
  file the schema load will fail — see `feedback_mysql_skip_ssl` in
  the maintainer's private notes, or ping the maintainer.

- **HEIC upload does not preview.** The client converts HEIC to JPEG
  via `heic2any` in the browser, the server re-encodes through Imagick.
  If Imagick is missing on your platform the server falls back to GD;
  that path is tested but noticeably slower on very large photos.

- **`npm run build` fails locally on macOS with an esbuild architecture
  error.** Known issue between macOS host and Linux Docker esbuild
  binaries. Not a bug in this repo. Use `npx vue-tsc --noEmit` to
  typecheck without building.

- **Sidebar last-item click does not register.** Reka UI's
  `SidebarGroupLabel` reserves layout space even when collapsed. The
  fix is already applied (`pointer-events-none` on the label); if it
  ever regresses see `feedback_sidebar_label_pointer_events`.

## 8. Running tests

Tests run against a dedicated `laravel_test` database with a runtime
guard — see `tests/TestCase.php`. **Never disable the guard.** The dev
database was wiped once by a `RefreshDatabase` on the wrong connection.

```bash
# Backend (Pest)
docker exec laravel-app composer test

# Backend, single file
docker exec laravel-app ./vendor/bin/pest --filter=DrinkScore

# Frontend (Vitest)
npm test

# Typecheck (vue-tsc)
npm run typecheck

# E2E (Playwright — spins up its own preview server)
npx playwright test
```

If Playwright complains about missing browsers, run once:

```bash
npx playwright install chromium
```

## 9. What to read next

- `CLAUDE.md` — project-wide conventions, data model, feature list
- `docs/ARCHITECTURE.md` — subsystem internals
- `docs/CONTRIBUTING.md` — branch model, commit convention, PR flow
- `docs/SAFETY_LEVER.md` — pre-push hook, decisions log, branch protection
- `SECURITY.md` — vulnerability disclosure
