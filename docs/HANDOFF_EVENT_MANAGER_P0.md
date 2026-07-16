# Handoff — Event Manager Role, P0 cleanup

_Session 2026-07-17. For the next agent (fresh session or another tool). Your memory of this
session is gone — this file + `docs/EVENT_MANAGER_ROLE_PLAN.md` (esp. its "Handoff / current status"
section and §11) + `AGENTS.md` are the whole context._

## What this work is

P0 of the Event-Manager-Role plan: **multi-tenancy / data-safety cleanup** done _before_ any role
code. Each checkpoint is one green-CI, independently-shippable bug-fix commit. Order A→B→C→D→E; the
only hard dependency is **D before E**.

## Done this session (CI green, NOT committed — André commits himself)

- ✅ **Checkpoint A — P0.5** (user deletion / FK RESTRICT) — already committed earlier (`cb1fe74`).
- ✅ **Checkpoint B — P0.2** — `DrinkController::destroy` cross-event guard.
  - `app/Http/Controllers/DrinkController.php`: added
    `abort_if($drink->event_id !== $this->activeEvent()?->id, 403)`.
  - `tests/Feature/Drinks/DrinkCatalogTest.php`: +1 test (`rejects deleting a drink from a foreign event`).
- ✅ **Checkpoint C — P0.4** — photo `uploader_role` snapshot.
  - `database/migrations/2026_07_17_120000_add_uploader_role_to_photos.php` — nullable `uploader_role`
    (VARCHAR 32) + backfill of existing user uploads via the primary-owner heuristic
    (`owner` vs `event_manager`); guest uploads stay null.
  - `app/Models/Photo.php` — `uploader_role` added to `$fillable`.
  - `app/Http/Controllers/PhotoController.php` — `store()` writes the snapshot
    (`$request->user()->id === $event->user_id ? 'owner' : 'event_manager'`); `index()` badge now reads
    the column (`$photo->uploader_role ?? 'owner'`); the now-unused `$ownerId` was removed.
  - `resources/js/pages/Photos/Index.vue` — `organizer_role` union widened to
    `owner|event_admin|event_manager|superadmin|null`; new `organizerLabel()` helper (owner/superadmin →
    "Veranstalter", event_admin/event_manager → "Mitveranstalter"). Both render sites use it.
  - `tests/Feature/Photo/PhotoWebTest.php` — +3 tests (owner snapshot, co-organizer snapshot, badge read).
  - `CLAUDE.md` — Photo data-model line updated.

### ⚠️ Deliberate P0.4 scope note (do not treat as a bug)
The plan's §9 wants the snapshot to match `roleOn()` exactly, but the pivot `role` column and
`roleOn()` land in **P1**. So at P0 the write uses the primary-owner heuristic (owner vs
`event_manager`, which is the P1 pivot default → forward-consistent, no re-migration needed). The
column union already permits the full `owner|event_admin|event_manager|superadmin` set. **P1 TODO:**
replace the `PhotoController::store` write with `roleOn()` (superadmin precedence + real event_admin
tier) and decide the label a superadmin-on-foreign-event upload shows.

## Commit messages handed over (English, house style — André commits)

**Checkpoint B:**
```
fix(drinks): guard drink deletion against cross-event access

DrinkController::destroy deleted any drink by route-bound ID with no
event check, letting a member of one event delete another event's drinks.
Add an active-event guard (403) + a cross-event Pest test.
```

**Checkpoint C:**
```
feat(photos): snapshot the uploader role on each photo

The organizer badge was derived dynamically as owner-vs-everyone-else,
which mislabels co-owners as "Mitveranstalter". Store uploader_role at
upload time (nullable, backfilled via the primary-owner heuristic) and
read it for the badge. Column allows the full owner|event_admin|
event_manager|superadmin union; P1 replaces the write with roleOn().
```

## Next: Checkpoint D — P0.1 (the big one), then E — P0.3

**D must precede E.** Full spec in `docs/EVENT_MANAGER_ROLE_PLAN.md` §11 P0.1 / P0.3 and the review
rounds §13.3–§13.5 — read them, they contain hard corrections. Key facts verified this session:

- **`food_specials` today:** columns `id, name, translation_key, timestamps` — **no `event_id`**. It
  is a globally *seeded* i18n template catalog (`2026_03_31_000002_..._seed_catalog.php`).
- **`categories` today:** columns `id, title, timestamps` — **no `event_id`**. Never a seeded catalog.
- **`GuestController` unscoped validation** (the P0.3 hole) at
  `app/Http/Controllers/GuestController.php:34,36,107,109`: `exists:groups,id` /
  `exists:food_specials,id` accept a **foreign event's** row. Also `:96` `FoodSpecial::all()` read
  must become "templates ∪ event-local".

**Decided data model (do NOT re-open — §11 P0.1, §13.4 #2, §13.5 #1):**
- `food_specials.event_id` **nullable**: null = global read-only template (auto-visible to every
  event), non-null = event-local. FK **`cascadeOnDelete()`, NOT `nullOnDelete()`** (nullOnDelete would
  leak a private local row into a global template on event deletion).
- `categories.event_id` **NOT NULL** (no templates). Migration must assign every existing category an
  event (orphans → default event `2026_03_16_130000`) before adding the NOT NULL + FK (cascade).
- **Migration must be staged, not one transaction** (MariaDB implicit-commits on ALTER): nullable
  column → idempotent backfill/clone-and-repoint of rows shared across events → validation query
  (fail loudly) → FK last. DB backup + staging dry-run (data surgery).
- **P0.3 validation:** groups strict `Rule::exists('groups','id')->where('event_id', $active)`;
  food_specials must ALSO accept templates: `event_id IS NULL OR event_id = $active`; categories
  strict (no `orWhereNull`).
- **New-event defaults:** nothing to seed — null templates are auto-visible. Just confirm
  `DemoDataSeeder` + reads still resolve against "templates ∪ event-local".

## Operational rules (not obvious from the code)

- **Never commit / push / deploy.** Hand back English `type(scope): summary` commit messages; André
  commits. (Full hard rules: `AGENTS.md`, and the memory feedback files.)
- **Tests run ONLY against `laravel_test`** (a `TestCase::setUp()` guard throws otherwise), inside the
  `laravel-app` container, with an explicit APP_KEY (the tracked `.env.testing` has an empty one):
  ```
  docker exec laravel-app sh -c 'APP_KEY="base64:WbW7jGEyKUY4O+DvQYyOZ8nteSTHdQ4VDYCcpqJwdHs=" ./vendor/bin/pest'
  ```
- **PHP format:** `docker exec laravel-app ./vendor/bin/pint --test <files>`.
- **Frontend checks run in the Node-20 container `eventplaner-vite-1`** (workdir `/var/www`; host
  Node 16 breaks them): `docker exec eventplaner-vite-1 sh -c 'npx prettier --check <f> && npx eslint
  <f> && npx vue-tsc --noEmit'`. `npx prettier --write` to fix.
- **`npm run build` fails locally** (esbuild macOS↔Linux) — known, use `vue-tsc` typecheck instead.
- **Verify CI green (pest + pint + prettier/eslint/vue-tsc) BEFORE handing over a commit message.**

## Full-suite baseline at end of this session
`pest`: **331 passed, 1 skipped** (1038 assertions). `pint`, `prettier`, `eslint`, `vue-tsc`: all green.
