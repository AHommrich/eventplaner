# Event Manager Role — Implementation Plan

Status: **P0–P7 complete; implementation finished** · Author: André + Claude · Created: 2026-07-16

A large extension that turns the existing (undifferentiated) "Mitveranstalter / co-organizer"
concept into a scoped **Event Manager** role, and adds a **Notes & ToDos** subsystem with
**owner → manager assignment + push notifications**, plus **in-app photo deletion** for both roles.

---

## Handoff / current status (read this first)

**For a fresh agent (new session or another tool) picking up this work.** Your memory of earlier
turns is gone — this section + `AGENTS.md` + the plan below are the whole context.

**Where we are:** **P0 complete + committed** (A–E, up to `9a23e4c`). **P1 (authorization backbone),
the §8.1 `/admin/users` split, P2 (Notes & ToDos, web), and P3 (My Events overview + switcher role
badges) are all done, CI-green, and committed + pushed** to `origin/develop` as a single commit
`5b22446` (message labels it P3, but the commit bundles all four units — 44 files). Full suite
**377 passed, 1 skipped**; pint/prettier/eslint/vue-tsc green at that commit.

**P4 and P5 plus their follow-up hardening are implemented and locally verified in the working tree,
but not committed/pushed.** Password
login, actor isolation, `X-Event-ID` re-authorization, event bootstrap, Notes/Photos management
APIs, one-time device pairing + web QR/device revoke, and access/approval token revocation are done.
P5 adds the Expo processor/privacy documentation, push register/opt-out API, ticket/receipt
lifecycle, privacy-minimized assignment job and scheduled queue/receipt processing.
The post-hardening backend run reports **446 passed, 1 skipped**; Pint and the required Vue checks
are green.

**P6 plus its follow-up hardening are implemented and locally verified in the separate Expo repo
(`eventplaner-app`), also not committed or pushed.** Organizer onboarding uses one-time QR pairing with a session isolated from guest access;
the active event scopes every management request. Mobile Notes/ToDos, cross-gallery photo
management, Expo token registration/opt-out and assignment-notification routing are complete.
The post-hardening mobile run reports **308 passed** across 52 suites; TypeScript and Prettier are
green, and ESLint has zero errors (three unrelated pre-existing warnings).

**P7 is complete in both uncommitted working trees.** The backend and mobile READMEs now describe
the role tiers, isolated organizer auth, event-scoped management tools and privacy-minimized push
flow. The mobile Store Listing copy distinguishes accountless Guest access from Organizer pairing
through an approved web account, and the backend `CLAUDE.md` mobile inventory reflects P6.

**⚠️ Not yet promoted to staging/production.** Per the branch workflow, a deploy still needs
`develop → staging → production` (sequential, one Coolify redeploy at a time). **Before that: two
prod-data prerequisites** — (1) the P0.1 food_specials backfill dry-run on staging, and (2) review
production `event_user` rows, because the P1 `event_manager` backfill silently downgrades today's
full-access co-organizers (promote spouses/partners to `owner` first). Neither is done yet.

**P3 (My Events + role badges) — delivered this session:**

- `resolveAccessibleEvents` now carries `my_role` per event (selects `user_id` for the roleOn check).
- Reusable `RoleBadge.vue` (superadmin rendered as Owner — internal tier). New `/settings/events`
  page (`pages/settings/Events.vue`) in the settings layout listing every accessible event with role
  badge + switch action; nav item added to `settings/Layout.vue`. Role badge also shown in the
  sidebar event-switcher dropdown. DE/EN locale keys. Test: `MyEventsTest`.

**Next up:** human commit(s), then the documented staging/production prerequisites and sequential
promotion flow.

**✅ Hardening completed and locally verified (2026-07-17):** the device/push revocation lifecycle,
assigned-note integrity, session inventory/TTL, role revocation, retention and app lifecycle findings
are implemented across both repositories. The exact decisions, acceptance criteria and verification
result live in **`docs/EVENT_MANAGER_HARDENING_PLAN.md`**. Production promotion and its data review
prerequisites remain separate and have not been performed.

### P7 implementation record

1. ✅ **Backend public overview:** EN/DE READMEs now surface Owner/Event-Admin/Event-Manager tiers,
   actor-isolated mobile management auth, per-request `X-Event-ID` authorization, Notes/ToDos,
   photo management and optional Expo pushes; architecture summary and test counts are current.
2. ✅ **Mobile public overview:** EN/DE READMEs cover both Guest and Organizer modes, the shared
   actor-aware API layer, separate SecureStore sessions, push credentials and privacy-minimized
   payload boundaries.
3. ✅ **Store accuracy + source-of-truth sync:** Store Listing access/privacy copy distinguishes
   passwordless Guest access from approved Organizer accounts. Native Organizer onboarding is
   pairing-only; password plus OAuth account login remains one coupled follow-up. `CLAUDE.md` and
   the mobile Architecture wording match the implemented P6 surface.

### P6 implementation record

P6 was implemented as four checkpoints in the separate Expo repository:

1. ✅ **Organizer auth + event bootstrap:** mutually exclusive guest/management SecureStore
   sessions; one-time pairing QR, authorized-event picker and automatic `X-Event-ID` injection for
   scoped management reads and writes. The backend password endpoint remains available, but the
   native app defers direct account login until password and OAuth can ship together.
2. ✅ **Notes & ToDos:** visible personal/assigned/team lists, create/assign, done toggle and
   policy-aligned delete affordances; notification-opened notes are highlighted.
3. ✅ **Photo management:** all active-event albums and photos are browsable; confirmed deletion
   uses the management endpoint whose backend authorization and observer remove the database row
   and object-storage blob.
4. ✅ **Push lifecycle:** SDK-compatible `expo-notifications` integration; explicit organizer opt-in,
   PAT-bound registration/rotation, queued offline logout revocation, foreground handling and
   notification tap → event switch + matching note. Dependency data flows and SecureStore keys are
   documented.

Tests cover the API adapters and screens for every checkpoint plus session isolation, header
injection, push permission/registration/opt-out and notification routing. After removing the
password-only native surface, the pre-hardening mobile suite reported **298 passed** across 52
suites. This is historical context, not verification of the current hardening tree.

### P5 implementation record

P5 was implemented after P4 in the same uncommitted working tree:

1. ✅ **Governance first:** Expo/650 Industries, US processing, incorporated SCCs and the
   Apple/Google delivery chain are documented in the processor register and public DE/EN privacy
   policy; README, CLAUDE and Architecture are synchronized.
2. ✅ **Token API + opt-out:** `push_tokens`; user-scoped
   `POST /api/management/push/register` (no `X-Event-ID`) upserts an Expo token or deletes the
   caller's token with `enabled=false`. Token metadata is included in the Art.-15 export.
3. ✅ **Two-phase delivery:** `ExpoPushService` batches sends (100), persists successful IDs in
   `push_tickets`, fetches receipts later (1000), removes `DeviceNotRegistered` tokens, expires
   unavailable receipts after 24 hours and prunes resolved diagnostics after 7 days.
4. ✅ **Assignment trigger:** `NotifyAssignedNote` is queued only for a new/changed assignee and
   re-checks the unchanged assignment, verified/approved User and active event-manager membership.
   Payloads contain generic copy + technical IDs, never task/event/guest/actor content.
5. ✅ **Operations:** the existing scheduler dispatches receipt processing every five minutes and
   drains the low-volume database queue once per minute; no extra daemon in the web container.

Tests: `ManagementPushTokenTest`, `ExpoPushServiceTest`, `NotifyAssignedNoteTest`, plus privacy and
Art.-15 export assertions. Final combined P4/P5 suite: **433 passed, 1 skipped**.

### P4 kickoff / implementation record

**Read, in order:** `AGENTS.md` (hard rules + execution env + working style) → this
handoff → §4.4 (management API namespace + `X-Event-ID` contract) → §6.1 (User login,
pairing QR, actor-type isolation) → §11 P4 (scope) → §2.4a table (cross-event guards).
Then `CLAUDE.md` for conventions. The design is locked — do not re-open the decisions
annotated "Codex #N … resolved/verified"; implement them.

**P4 is complete in the uncommitted working tree.** It was implemented as these independently
green checkpoints:

1. ✅ **`HasApiTokens` on `User`** + `POST /api/auth/login` (email+password → Sanctum
   token) gated by email-verified + `is_approved` + `throttle`. + Pest.
2. ✅ **Actor-type isolation** — guest middleware rejects non-`Guest` tokenables;
   management side rejects non-`User`. Mint User tokens with a `management:*` Sanctum
   ability; check type **and** ability (defence in depth). + Pest (guest token on a
   management route → 403, and vice versa).
3. ✅ **`ResolveManagementEvent` middleware** — resolves the event from the **`X-Event-ID`
   header** and re-checks, _per request_: tokenable is `User`, email verified,
   `is_approved`, token ability, and `roleOn($event) !== null` (use `roleOn()`/the
   `EventPolicy`, **NOT** a raw `event_user` query — the primary owner lives in
   `events.user_id` and is often absent from the pivot). Then the endpoint's tier
   (`manage`/`administer`) must pass. Any failure → 403 (401 for auth). + Pest.
4. ✅ **`GET /api/management/me/events`** (accessible events + `my_role`; the only
   event-scoped endpoint exempt from `X-Event-ID`).
5. ✅ **Notes API** — `GET/POST/PATCH/DELETE /api/management/notes` reusing the exact
   `NoteController` authorization already built in P2 (assignee = read + toggle
   `is_done` only; `assignNote` for assignment). + Pest.
6. ✅ **Photo management API** — `GET /api/management/photos`, `DELETE …/{id}` + batch
   (§7); separate from the guest `/api/photos` (which is taken, `EnsureGuestHasAppAccess`).
7. ✅ **Pairing QR** (`device_pairings`: hashed token, TTL, atomic single-use redeem,
   `personal_access_token_id` FK for per-device revoke) — see §6.1 hygiene list. + Pest.
8. ✅ **Token revocation on access change** — removing a user from an event / un-approving /
   deactivating must delete their Sanctum tokens (or those scoped to that event).

All management endpoints live under `/api/management/*` (guest `/api/*` is taken). Pairing
metadata is included in the Art.-15 export without secret/token values. `CLAUDE.md`,
`docs/ARCHITECTURE.md`, and `docs/DECISIONS.md` contain the shipped contract and rationale.

**P2 (Notes & ToDos, web) — delivered this session:**

- Migration `2026_07_17_160000_create_notes_table` (soft deletes; nullable `author_user_id`/
  `assignee_user_id` + `nullOnDelete`; `author_name` snapshot; indices). `Note` model + `NoteFactory`;
  `Event::notes()`; `RETENTION_NOTES_DAYS` config key.
- `EventPolicy::assignNote` (event_admin ∪ owner ∪ superadmin). `NoteController` (index/store/update/
  destroy) with cross-event guards, active-manager assignee validation, assignee = read + toggle
  `is_done` only (any other field → 403), server-side type/transition validation. Routes under manage.
- `pages/Notes/Index.vue` (My + Assigned-to-team lists, todo checkbox, hide-done filter) + sidebar nav
  item + DE/EN locale keys.
- `app:prune-notes` command + schedule (Sun 03:55); `UserDataExporter` extended (notes incl.
  soft-deleted with `deleted_at` marker). Tests: `NoteTest` (9), `PruneNotesTest` (2),
  `DataExportTest` (+1). Docs: CLAUDE.md data model, DECISIONS.md, ARCHITECTURE (pending §2a note).
- **Not in P2:** mobile management API (now delivered in P4), Expo push + `NotifyAssignedNote` (P5).

**P1 delivered (earlier this session):**

- `event_user.role` migration (`2026_07_17_150000`, default `event_manager`); `User::roleOn()`/
  `canManage()`/`canAdminister()`; `Event::owners()`/`isOwnedBy()`; `withPivot('role')`.
- `app/Policies/EventPolicy.php` (first policy) + `can_administer` middleware + registration in
  `AppServiceProvider`; base `Controller` now uses `AuthorizesRequests`.
- `routes/web.php` split into manage vs. administer (per-route `can_administer` on projector config,
  `groups/schedule-visibility`, `DELETE guests/{guest}`).
- `EventAccessController` rewritten (roles, invite-with-role, `updateRole`, policy-gated remove) +
  `App\Services\EventAccessService` (transactional last-owner invariant). New route `event.access.role`.
- `RequestController`: photo-reports → administer (resolve/delete + `index` omits them for managers).
- `HandleInertiaRequests`: shares `active_event.my_role`; photo-report badge gated to administer.
- Frontend: `AppSidebar.vue` role gating; `Event/Access.vue` role selector + copy; locale role labels.
- Tests: `EventPolicyTest` (7) + `EventRoleGatingTest` (18). Full suite **361 passed, 1 skipped**;
  pint/prettier/eslint/vue-tsc green.

**§8.1 `/admin/users` split — DONE this session:** `/admin/users` is now purely global user admin
(role + delete); all per-event access + tiers moved to `/event/access`. Dropped `addToEvent`/
`removeFromEvent` + routes + the `event_access`/`events` payload; unused admin locale keys removed;
`UserAdminSplitTest` added. **Before prod deploy:** review production `event_user` — the
`event_manager` backfill silently downgrades today's full-access co-organizers (promote
spouses/partners to `owner`).

**Historical P0 checkpoints (all done + committed):**

- ✅ **Checkpoint A — P0.5 (user deletion / FK RESTRICT)** — DONE & COMMITTED (`cb1fe74`).
- ✅ **Checkpoint B — P0.2 (`DrinkController::destroy` cross-event guard)** — DONE, CI green,
  **not yet committed** (André commits himself). Files: `DrinkController::destroy` (added
  `abort_if($drink->event_id !== $this->activeEvent()?->id, 403)`),
  `tests/Feature/Drinks/DrinkCatalogTest.php` (+1 test). Commit message handed over below.
- ✅ **Checkpoint C — P0.4 (photo `uploader_role` snapshot)** — DONE, CI green, **not yet committed**.
  Files: migration `2026_07_17_120000_add_uploader_role_to_photos.php` (nullable column + primary-owner
  backfill), `app/Models/Photo.php` (fillable), `PhotoController` (`store` writes snapshot; `index`
  badge reads the column, `$ownerId` removed), `resources/js/pages/Photos/Index.vue` (union +
  `organizerLabel()` helper), `tests/Feature/Photo/PhotoWebTest.php` (+3 tests), `CLAUDE.md`
  (Photo data model). **Note:** the P0 write uses the primary-owner heuristic (owner vs.
  `event_manager`, the P1 pivot default); the column union already allows the full
  `owner|event_admin|event_manager|superadmin` set. **P1 must replace the write with `roleOn()`**
  (superadmin-precedence + real tier), and decide the superadmin-on-foreign-event label.
  Commit message handed over below.
- ⏭️ **Next: Checkpoint D — P0.1** (food_specials/categories delta migration + controller/read
  scoping — the big one) which **MUST precede E (P0.3 GuestController reference validation)**.

**Locked decisions not to re-open:** delta/template model for `food_specials` (`event_id` nullable,
null = global read-only template, FK `cascadeOnDelete` NOT `nullOnDelete`) vs `categories.event_id`
NOT NULL; role model, `changeAccess` checkpoint, per-request management-token revalidation. Full
rationale + 6 review rounds in §12/§13.x.

**Operational rules a new agent MUST know (these are not obvious from the code):**

- **Never commit, push, or deploy.** Hand back an English `type(scope): summary` commit message;
  André commits. (Full hard rules: `AGENTS.md`.)
- **Tests run ONLY against `laravel_test`** (a `TestCase::setUp()` guard throws otherwise). Run inside
  the `laravel-app` Docker container, and pass an APP_KEY because the tracked `.env.testing` has an
  **empty** `APP_KEY` (else `MissingAppKeyException`):
    ```
    docker exec laravel-app sh -c 'APP_KEY="base64:WbW7jGEyKUY4O+DvQYyOZ8nteSTHdQ4VDYCcpqJwdHs=" ./vendor/bin/pest'
    ```
    PHP formatting: `docker exec laravel-app ./vendor/bin/pint --test <files>`.
- **Frontend checks need the right Node:** run `source ~/.nvm/nvm.sh && nvm use && …` first (host
  default Node 16 breaks prettier/eslint/vue-tsc), or use the `eventplaner-vite-1` (Node 20)
  container. P0 is backend-only so far, so no Vue checks have been needed yet.
- **Verify CI green (pest + pint, and prettier/eslint/vue-tsc once Vue changes land) BEFORE handing
  over a commit message.**
- **`npm run build` fails locally** (esbuild macOS↔Linux) — known pre-existing, use
  `npm run typecheck` instead.

_(Recommendation for André, not done unasked: `.env.testing` is committed with an empty `APP_KEY` —
adding a throwaway base64 test key there would remove the per-run override and unbreak `composer
test` out of the box.)_

---

## 0. Current state (what already exists — do NOT rebuild)

The role foundation is **half-built already**. This project is a _refinement_, not a greenfield.

| Piece                                      | Where                                                                                     | Today's behaviour                                                |
| ------------------------------------------ | ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| Pivot `event_user` (`event_id`, `user_id`) | `database/schema`                                                                         | Grants a user access to an event. **No `role` column.**          |
| `Event::users()` / `User::sharedEvents()`  | `app/Models/*.php`                                                                        | belongsToMany over the pivot                                     |
| `User::accessibleEvents()`                 | `User.php:60-68`                                                                          | owned (`user_id`) OR pivot member; admins see all                |
| Invite flow                                | `EventAccessController` + `Event/Access.vue` (`/event/access`)                            | Owner/Admin invites co-organizers via `syncWithoutDetaching`     |
| Access resolution + Inertia share          | `HandleInertiaRequests.php:42-67, 159-160`                                                | resolves `active_event`, shares `accessible_events`              |
| `has_event` middleware                     | `EnsureHasEventAccess.php`                                                                | passes if admin OR any accessible event                          |
| `admin` middleware                         | `EnsureUserIsAdmin.php`                                                                   | passes if `user.role === 'admin'`                                |
| Photo badge                                | `Photos/Index.vue` (`organizer_role`: `owner` / `co_organizer`), `PhotoController.php:56` | shows "(Mitveranstalter)" on uploads                             |
| Locale                                     | `de.json/en.json` key `coOrganizer`, `coOrganizerInfo`                                    | label "Mitveranstalter"; info text says **"same access as you"** |

**Key gap:** pivot members currently reach the _entire_ `has_event` route group, which
**includes administration** (`/event/settings`, `/app/design`, `/schedule`). The `coOrganizerInfo`
string literally promises "denselben Zugang wie du". So the work is to **restrict** this role, not
grant it. Authorization is done inline (`abort_if`) — **there are no Laravel Policies yet.**

---

## 1. Decisions (locked)

- **Platform:** Web + Mobile. Managers get a new **management login in the Expo app** + **Expo push**.
- **Co-ownership (first-class):** an event may have **multiple equal owners** — required for the
  primary use case (a wedding couple: André _and_ Tabea are both full owners, not owner + helper).
  Ownership becomes a pivot role, not just the single `event.user_id`.
- **Per-event roles:** below the owner(s) — **Event-Admin** (full administration, minus ownership
  transfer/delete/granting) for non-couple helpers (e.g. a planner), and **Event-Manager** (scoped:
  manage, not administer) for e.g. best-man/maid-of-honour. Owners (and superadmin) assign tiers.
- **Naming:** UI labels **"Event-Admin"** and **"Event-Manager"**; internal keys `event_admin` /
  `event_manager`. Never label the per-event admin just "Admin" (collides with global superadmin).
- **Architecture:** pivot `role` column + a central **`EventPolicy`** (replaces scattered `abort_if`).
- **Event settings split:** Manager may toggle **games on/off**, but NOT the deep event settings;
  Event-Admin may.

### Role → capability matrix

Columns Owner / Event-Admin / Event-Manager are **per-event** roles. **Owner covers every owner of
the event** — the primary `event.user_id` _and_ any co-owner (pivot role `owner`); all owners are
equal. "Superadmin" is the **global** `users.role='admin'` — a different axis entirely (see below).

| Capability                                                        |  Guest  | **Event-Manager** | **Event-Admin** | Owner | Superadmin (global) |
| ----------------------------------------------------------------- | :-----: | :---------------: | :-------------: | :---: | :-----------------: |
| Guest management (add/edit, `app_access`/`drinks_access` toggles) |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Reset a guest's app login · reset a guest's drink logs            |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| **Delete a guest entirely** (`guests.destroy`)                    |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| Invitations                                                       |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Drinks + drink game (manage)                                      |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Photo game (manage)                                               |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Photos / galleries (view + delete any)                            | limited |        ✅         |       ✅        |  ✅   |         ✅          |
| RSVP revocation requests (approve/decline)                        |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Photo reports (see + resolve guest complaints)                    |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| **Toggle games on/off**                                           |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| Notes / ToDos (own)                                               |    –    |        ✅         |       ✅        |  ✅   |         ✅          |
| **Deep event settings** (name/date/venue/colors/cover/fonts)      |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| Timeline / schedule                                               |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| App design                                                        |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| Assign ToDos/notes to a manager                                   |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| Manage access: add/remove members, assign **Manager** role        |    –    |        ❌         |       ✅        |  ✅   |         ✅          |
| Grant/revoke the **Event-Admin** tier                             |    –    |        ❌         |       ❌        |  ✅   |         ✅          |
| **Grant/revoke co-owner, transfer ownership, delete the event**   |    –    |        ❌         |       ❌        |  ✅   |         ✅          |
| Global user management (`/admin/users`), grant **global** admin   |    –    |        ❌         |       ❌        |  ❌   |         ✅          |

Authorization tiers over an event (owner = `event.user_id` **or** pivot role `owner`):

- **`manage`** = event_manager ∪ event_admin ∪ owner ∪ superadmin
- **`administer`** = event_admin ∪ owner ∪ superadmin
- **owner-only** = grant/revoke co-owner, transfer ownership, delete event (∪ superadmin),
  guarded by **last-owner protection** (an event must always keep ≥1 owner).

**Hard security boundary:** an Owner/Event-Admin can grant _per-event_ roles only. **Granting the
global `users.role='admin'` stays superadmin-only** — no event-level actor can escalate someone to
platform superadmin.

### Two distinct role axes (do not conflate)

- **Global system role** — `users.role` (`admin` | `user`). Set only on `/admin/users` by a
  superadmin. Decides platform-wide superadmin. **Not** grantable by owners.
- **Per-event role** — Owner = `event.user_id`; Event-Admin / Event-Manager = `event_user` pivot
  `role`. Assigned by the owner (or superadmin, or — per decision — an event-admin) on the
  event-scoped access page.
- **P1 adds** the per-event role selector (**Event-Admin / Event-Manager**) to the access UI and
  shows the per-event role separately from the global role.

---

## 2. Authorization foundation (backend)

### 2.1 Pivot role column

- Migration: add `role` to `event_user`, `VARCHAR(32) NOT NULL DEFAULT 'event_manager'`.
  Allowed values: **`owner`** | `event_admin` | `event_manager`. **Backfill:** existing rows are
  full-access co-organizers today — target tier is a **rollout question**, not automatic (§11).
  Safe default `event_manager`, but review production rows first; a spouse/partner should be `owner`.
- **Co-ownership model:** ownership = `event.user_id` (the _primary_ owner: creator/anchor, kept for
  backward-compat + last-owner anchoring) **OR** any `event_user` row with `role = 'owner'`. All
  owners are equal in capability. For the wedding: André = `event.user_id`, Tabea = pivot `owner`.
    - `event.user_id` stays non-null (an event always has a primary owner). **Transferring ownership**
      = swap `event.user_id` to another current owner. **Last-owner protection**: cannot demote/remove
      the last remaining owner (counting the primary).
    - `User::accessibleEvents()` already includes pivot members, so co-owners appear automatically;
      only the **role resolution** must map pivot `owner` → owner tier.
- Update `Event::users()` / `User::sharedEvents()` with `->withPivot('role')`. Add
  `Event::owners()` (primary ∪ pivot `owner`) and `Event::isOwnedBy(User)`.
- Helpers on `User`:
    - `roleOn(Event): 'owner'|'event_admin'|'event_manager'|'superadmin'|null`
      (precedence: superadmin → `event.user_id`/pivot `owner` → pivot `event_admin`/`event_manager`).
    - `canManage(Event): bool`, `canAdminister(Event): bool` (thin wrappers over the policy).

### 2.2 `EventPolicy` (new — first policy in the project)

- `app/Policies/EventPolicy.php` abilities:
    - `view` = any role on the event.
    - `manage` = event_manager ∪ event_admin ∪ owner (∪ superadmin).
    - `administer` = event_admin ∪ owner (∪ superadmin).
    - `manageAccess` = owner (∪ superadmin) ∪ event_admin — the coarse "may open the access screen"
      gate. **The actual add/change/remove decision needs target context (Codex round-5 #1):**
      `manageAccess(Event)` alone cannot tell "event_admin invites a manager" (allowed) from
      "event_admin promotes someone to owner" (forbidden). So the real rule lives in one **central,
      server-side checkpoint**, not scattered across controller/frontend:
        - `EventPolicy::changeAccess(User $actor, Event $event, ?User $target, string $newRole)`
          (or a dedicated `EventAccessService::apply(...)` the controller calls). Invite, role-change and
          remove all route through it. Rules:
            - actor must pass `manageAccess`;
            - **event_admin** may only create/modify/remove **`event_manager`** rows — never grant/revoke
              `event_admin` or `owner`;
            - granting/revoking `event_admin` and granting `owner` = **owner ∪ superadmin** only;
            - never assign the global `users.role='admin'` here (superadmin stays superadmin-only).
        - Frontend gating is cosmetic only; the checkpoint is authoritative.
    - `grantOwner` (make someone a co-owner) = owner ∪ superadmin only.
    - `transferOwnership` / `deleteEvent` = owner ∪ superadmin only.
    - "owner" throughout = `Event::isOwnedBy($user)` (primary `event.user_id` or pivot `owner`).
- **Last-owner protection — scope precisely (Codex #2, resolved):** it guards against **losing the
  last owner by accident**, i.e. it blocks _demotion / removal / transfer-away_ that would leave the
  event with zero owners. It does **NOT** block **deliberate event deletion** by its sole owner —
  deleting the whole event is a legitimate owner action and cascades everything. So: `deleteEvent` is
  _not_ subject to last-owner protection; only `manageAccess`-demotion and `transferOwnership` are.
- **Every owner-count-changing op must be transactional, not just transfer (Codex round-4 #4):** a
  bare `owners()->count() > 1` check before a detach/demote races under concurrent requests (two
  parallel "remove the other owner" calls both read count=2, both proceed → 0 owners). **Demote and
  remove** therefore follow the same pattern as transfer:
    1. `DB::transaction(...)` with `lockForUpdate()` on the event row + its `event_user` owner rows.
    2. Re-read the owner count **inside** the lock.
    3. Abort if the op would drop below 1 owner.
    4. Apply the role change / detach, then commit.
- **Ownership transfer must be one atomic transaction (Codex #2, resolved):** swapping
  `event.user_id` naively could strip the outgoing primary of ownership. Do it in a DB transaction:
    1. Validate the target is an existing member and will become primary.
    2. **Ensure the outgoing primary is preserved as a pivot `owner` row** (insert if missing) — so they
       stay a co-owner, not silently demoted.
    3. Swap `event.user_id` to the target.
    4. Remove the now-redundant pivot `owner` row of the _new_ primary (they're covered by
       `event.user_id`) — cosmetic, keeps `owners()` from double-counting.
       Assert `Event::owners()->count() >= 1` holds throughout.
- Global superadmin short-circuits `view/manage/administer/manageAccess` via a `before()` hook —
  but **not** `transferOwnership`/`deleteEvent` semantics beyond what the matrix allows, and never
  role-granting of the global tier.
- Register in `AppServiceProvider` (or auto-discovery).
- Route-middleware sugar: `can:administer,event`, plus a small `EnsureCanAdministerEvent` middleware
  for active-event routes that don't bind an `{event}` param (event resolved from session).

### 2.3 Route gating (`routes/web.php`)

Split today's single `has_event` group (currently `web.php:47-131`, everything in one block) into two:

- **Manage group** (`has_event`, unchanged membership): dashboard, guests add/edit/update + access
  toggles + RSVP + **`app-login` reset (`:128`)** + **`drink-logs` reset (`:130`)** (round-6 #4,
  decided: these two destructive guest ops stay `manage`), invitations, categories, groups
  create/destroy, foodspecials, drinks (except see below), photos view + `destroy`/`destroyBatch`,
  photo-game, requests (revocations/photo-reports scoped to the active event), game on/off toggles
  (§2.4).
- **Administer group** (`has_event` + new `EnsureCanAdministerEvent`): `/event/settings`,
  `/app/design` + cover, `/event/settings/style-presets`, `/schedule` (all), `/event/access`,
  event delete, and **`DELETE guests/{guest}` (`:123`)** — round-6 #4, decided: fully deleting a
  guest (with their photos/RSVP/logs) is administer-only, even though managers do everything else
  guest-related.

**⚠️ Per-route corrections (do NOT blanket-move by prefix — Codex #4, verified):**

- `photos/projector-album` (`web.php:58`), `photos/projector-name-mode` (`:59`),
  `photos/projector-token/regenerate` (`:60`) → **administer**. Regenerating the projector token
  _immediately locks out the running public projector screen_ — that is event configuration, not
  photo management. Managers get photo view/delete only.
- `groups/{group}/schedule-visibility` (`web.php:63`) → **administer** (it is timeline config; the
  capability matrix is authoritative — **Event-Admin ∪ Owner may manage the timeline/schedule**,
  Manager may not — even though the route lives in the groups controller). Not owner-only.
- `DELETE guests/{guest}` (`web.php:123`) → **administer**, but it sits **between** manager-allowed
  guest routes (edit/update/rsvp/access/login-reset/drink-logs on `:122-130`). So gate it **per-route**
  with `can:administer` (like the projector routes above), not by moving the whole guest block. The
  other guest routes stay `manage`.
- **`/requests` page — split visibility by section (round-6b #4b, decided: managers do NOT see photo
  reports).** The page stays `manage` because **revocation requests** are manager-handleable
  (`approve/declineRevocation` = manage). But the **photo-reports section** is `administer`: the
  `RequestController::index` must **omit** photo reports from the payload for a manager (not just hide
  in Vue), and the resolve/delete-photo actions stay `administer` (§2.4a). Rationale: don't surface a
  guest complaint to someone who cannot act on it. _(Flip to "manager sees read-only" only if André
  later wants managers aware of complaints.)_

### 2.4a Cross-event / controller authorization audit (P1 — MANDATORY, not just routes)

Route-group gating is necessary but NOT sufficient: several mutating controllers bind a model by ID
and must independently verify it belongs to the active event. **Audited 2026-07-16** — findings:

This is a **completeness checklist**, not a general sentence (Codex round-4 #9). Every mutating
method must have all three columns ✅ before P1 signs off: **object-ID belongs to active event** ·
**policy/guard applied** · **cross-event Pest test exists**.

| Controller · method                                                                     | Object/foreign-ID checked today                                                                               | Fix / phase                                                                                                                                                                                                                                   | Cross-event test                                                                          |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `DrinkController::destroy` (`:157-162`)                                                 | ❌ none — deletes any drink by ID                                                                             | **P0.2** add `abort_if($drink->event_id !== activeEvent()?->id, 403)`                                                                                                                                                                         | add                                                                                       |
| `CategoryController::store` / `FoodSpecialController::store`                            | ❌ no `event_id` at all — rows global                                                                         | **P0.1** real multi-event migration + scope controllers                                                                                                                                                                                       | add                                                                                       |
| `GuestController::store`/`update` (`:34,:36,:107,:109`)                                 | ❌ `exists:groups,id` / `exists:food_specials,id` — **foreign event's** group/food-special passes             | **P0.3** event-scoped `Rule::exists(...)->where('event_id', …)`                                                                                                                                                                               | add (foreign group_id rejected)                                                           |
| `GuestController::destroy` (`:123`)                                                     | needs event guard **and** tier bump                                                                           | **P1** → `administer`-only, per-route `can:administer` (round-6 #4, §2.3)                                                                                                                                                                     | add: **manager → 403**, **event_admin/owner → ok**, **foreign guest → 403** (round-6b #4) |
| `Admin/UserController::destroy` (`:60-67`)                                              | ❌ only self-delete; `events.user_id` is `ON DELETE CASCADE`                                                  | **P0.5** block delete while primary-owner; detach pivots; guard last-superadmin                                                                                                                                                               | add                                                                                       |
| `EventAccessController` index/invite/remove (`:21,:43,:70`)                             | ⚠️ `event.user_id === user->id` → primary owner OR superadmin only — even today's pivot co-organizers blocked | **P1** move to `manageAccess` policy (pivot `owner` + `event_admin` pass). _Previous "keep" was WRONG (Codex #1). No P0 interim — loosening early would let any member invite/remove (Codex round-4 #1); it stays owner/superadmin until P1._ | add                                                                                       |
| `RequestController::resolvePhotoReport` / `deletePhotoFromReport` (`:193-194,:216-217`) | ⚠️ `event?->user_id === user->id` → primary owner OR superadmin only                                          | **P1** = `administer` (owner ∪ event_admin ∪ superadmin). Manager deletes gallery photos directly (§7) but does not adjudicate guest complaints (decided)                                                                                     | add                                                                                       |
| `RequestController::approve/declineRevocation` (`:117,:137`)                            | ✅ active-event scoped                                                                                        | keep — `manage`-level                                                                                                                                                                                                                         | have                                                                                      |
| `GroupController` destroy / schedule-visibility (`:38,:53`)                             | ✅ present                                                                                                    | keep (schedule-visibility → **administer**, §2.3)                                                                                                                                                                                             | have                                                                                      |
| `PhotoController` (web) destroy/batch/projector-album (`:122,:140,:161`)                | ✅ present                                                                                                    | keep                                                                                                                                                                                                                                          | have                                                                                      |
| `PhotoGameController` destroyOverride/destroyAssignment (`:221,:232`)                   | ✅ present                                                                                                    | keep                                                                                                                                                                                                                                          | have                                                                                      |
| `InvitationTokenController` generateForGroup/Guest (`:53,:66`)                          | ✅ present                                                                                                    | keep                                                                                                                                                                                                                                          | have                                                                                      |
| `EventStylePresetController` update/destroy (`:54,:65`)                                 | ✅ present                                                                                                    | keep                                                                                                                                                                                                                                          | have                                                                                      |
| `Api/PhotoController::destroy` (`:113-118`)                                             | ✅ own-photo + app-gallery only                                                                               | confirms managers need a **separate** endpoint (§7)                                                                                                                                                                                           | have                                                                                      |

P1/P0 acceptance criterion: **every row above** has all three columns satisfied. The checklist is the
sign-off artifact — a general "all methods are guarded" claim is not acceptable.

### 2.4 Game on/off extraction (so managers can toggle without settings access)

- `photo_game_enabled` is today set **only** in `Event/Settings.vue` (owner-only after §2.3).
  `drink_game_enabled` is also editable from `Drinks/Index.vue`.
- Add two tiny `manage`-gated endpoints (e.g. `PATCH /drinks/game/toggle`,
  `PATCH /photos/game/toggle`) and surface the switches on the respective **feature pages**
  (Drinks/Index, PhotoGame/Index). Remove the game toggles from the owner-only settings form,
  or leave them there read/write for owners but treat the feature-page toggle as the manager path.

---

## 3. Frontend (web) — role awareness

- Share the active-event **role** through Inertia: extend `active_event` payload in
  `HandleInertiaRequests` with the **canonical role set** (Codex #6, consistent everywhere —
  §8 and the TS types must use the exact same union):
  `my_role: 'owner' | 'event_admin' | 'event_manager' | 'superadmin'`.
  (`superadmin` is derived from the global `users.role='admin'`, not from `event_user`; it is
  surfaced as a role so UI gates and badges are uniform. A user with no relation → `null`.)
- `AppSidebar.vue`: replace the ownership guess (`activeEvent.user_id === currentUserId`) with
  `my_role`. Show administer-only items (App design, Timeline, Manage-access, Event settings) for
  `owner`/`event_admin`/`superadmin`; hide for `event_manager`. Show manage items for all tiers.
- Guard the pages themselves too (defense in depth): administer pages redirect / show a
  "not allowed" state unless `canAdminister`.
- **`Event/Access.vue` gets a per-member role selector (Owner / Event-Admin / Event-Manager)** + a
  role display per member. `EventAccessController::invite` (`:47`) takes a `role` param; `EventPolicy`
  decides who may assign which tier (event_admin → managers only; owner/superadmin → any tier incl.
  co-owner). `remove`/demote enforces **last-owner protection**. Same selector in the `/admin/users`
  event-access block after the §8.1 split. The member list shows co-owners distinctly from admins/
  managers.
- Reword `coOrganizerInfo`: the promise "same access as you" is now false for managers — describe the
  scoped role; for event_admins it's accurate.

---

## 4. Notes & ToDos subsystem

### 4.1 Data model

- `notes` table: `id, event_id (FK, cascade), author_user_id (FK, **nullable, nullOnDelete**),
author_name (string, nullable — snapshot for readable history after anonymization),
assignee_user_id (FK, **nullable, nullOnDelete**), type ('note'|'todo'), title, body (nullable),
is_done (bool, todos only), done_at (nullable), timestamps`. - **FK nullability is mandatory (Codex #3, verified):** user deletion in this project is a **hard
  delete** (no SoftDeletes on `User`). If `author_user_id`/`assignee_user_id` are non-nullable FKs,
  deleting a user would throw on the FK constraint. Both columns MUST be `nullable()` +
  `->nullOnDelete()`. `author_name` snapshot lets the UI still show "created by …" after the
  author's account is gone. - `assignee_user_id = null` → personal (author's own). - `assignee_user_id = <manager>` → owner-assigned task (only owner/admin may create these).
  The assignee sees these labelled as **"created by the event organizer for you"**
  (de: "Vom Veranstalter für dich erstellt"). Assignee sees their _own_ assigned items;
  not those assigned to other managers.
- Model `Note` + relations; `Event::notes()`. Use **soft deletes**.
- **Integrity rules (Codex #6):**
    - Cross-event guard everywhere: a note's `event_id` must equal the active event.
    - On assign: `assignee_user_id` must be an **active manager of that event** (validate against
      `event_user` at write time) — reject stale/foreign users.
    - **Membership revocation:** removing a user from `event_user` must revoke their access to that
      event's notes immediately (access is checked live via the policy, not cached).
    - **User deletion:** `author_user_id` — keep the note but null/anonymize the author;
      `assignee_user_id` → `nullOnDelete`. Never cascade-delete an event's task list because one
      person left.
    - Server-side validation of `type` (`note`|`todo`) and legal state transitions
      (`is_done`/`done_at` only for `todo`); never trust the client.
    - Indices on `event_id`, `assignee_user_id`, `is_done`.

### 4.2 Authorization

- Create/read/update/delete personal note: author, via `manage` on the event.
- Create an **assigned** note (assignee set) — dedicated ability, unambiguous (Codex #4, resolved):
    - **`assignNote` = `event_admin` ∪ `owner` ∪ `superadmin`.** ("Admin" here is the **event_admin
      tier**, NOT the global superadmin-only reading — event_admins may assign tasks to managers.)
    - **Assignee = exclusively an _active_ `event_manager` of that event** (validated against
      `event_user` at write time; reject owners/admins/foreign/stale users).
    - **Assignee** may read the note and mark todos done; may **not** edit text, reassign, or delete it.
    - **Creator** (the assigner) may later edit/reassign/delete the assigned note (round-6 #3, decided).
      Read/edit/delete of assigned notes is gated by `assignNote` on the event — so any `event_admin`/
      `owner`/`superadmin` may manage them, not only the original creator (a co-owner must be able to
      clean up after another). Assignee stays read + complete only.

### 4.3 Web UI

- New page `pages/Notes/Index.vue` under the manage group. Two lists: **My notes/todos** and
  **Assigned to team** — the latter visible to **`event_admin` ∪ `owner` ∪ `superadmin`** (round-6
  #3: matches `assignNote`; §4.2 lets event_admins assign, so they must see the list too — not
  "owners only"). Simple: title + optional body, todo checkbox, filter done.
- Sidebar nav item under the manage section (both roles).

### 4.4 API (for mobile) — separate namespace, mandatory (Codex #1, verified)

The existing `/api/photos` (GET/POST/DELETE) is **already taken** by the guest app
(`routes/api.php:53-56`, guarded by `EnsureGuestHasAppAccess`). Reusing those paths/methods for
managers would collide/override. **All management endpoints live under `/api/management/*`:**

- `GET /api/management/notes`, `POST`, `PATCH /{id}`, `DELETE /{id}`.
- `GET /api/management/me/events` (accessible events + `my_role` per event).
- `GET /api/management/photos`, `DELETE /api/management/photos/{id}` + batch (§7).
- `POST /api/management/push/register` (§5).

**Active-event contract for bearer clients (Codex #2, verified):** the guest API infers the event
from the guest's single `event_id`; a **User** spans many events and a Sanctum bearer token carries
no web session. So every management request takes the target event from an **`X-Event-ID` header**,
resolved by a new `ResolveManagementEvent` middleware.

- **`X-Event-ID` is required for ALL event-scoped endpoints, not just writes (round-5 #5).** That
  includes the reads `GET /api/management/notes` and `GET /api/management/photos`. **The only
  exemption is `GET /api/management/me/events`** — the bootstrap call the app makes first to learn
  which events exist and pick one. (`push/register` is user-scoped, not event-scoped → no header.)
- **The middleware re-checks the full authorization on EVERY request (round-5 #5), because a Sanctum
  bearer outlives the login check.** Login (§6.1) verifies email + approval _once_; a token issued
  then stays valid even if the user is later disabled or loses access. These tokens can read/delete
  **guest and photo data**, so `ResolveManagementEvent` must, per request, assert:
    1. the tokenable is a **`User`** instance (actor-type isolation, §6.1) — reject Guest tokens;
    2. **email verified** (`hasVerifiedEmail()`);
    3. **`is_approved`** still true;
    4. the token carries the required **Sanctum ability** (`management:*`, §6.1);
    5. the User **currently** has a role on the `X-Event-ID` event, resolved via
       **`roleOn($event)` / the `EventPolicy`, NOT a raw `event_user` query** (round-6b #2, hard
       correction): the **primary owner lives in `events.user_id` and is often absent from
       `event_user`**, so a pivot-only lookup would lock André (primary owner) out of the mobile API.
       The check must pass for: global superadmin, `events.user_id === user.id`, **or** a matching
       `event_user` role — exactly what `roleOn()`/`isOwnedBy()` already encapsulate. Then the
       endpoint's policy tier (`manage`/`administer`) must pass for that resolved role.
       Any failing check → 403 (401 for auth issues). No header / no membership → 403.
- **On access revocation, delete the user's tokens (round-5 #5):** when a user is removed from an
  event, deactivated, or un-approved, revoke their Sanctum tokens (or at least those scoped to that
  event) so a cached bearer cannot keep operating until it happens to be re-checked.

These are **User-authenticated** (Sanctum), distinct from the **Guest**-token API — see §6.1.

---

## 5. Push notifications (Expo)

New infrastructure in this repo; mobile-side registration lives in the Expo repo.

- **Token storage:** `push_tokens` table (`user_id`, `expo_token`, `platform`, `last_used_at`,
  unique on `expo_token`). Endpoint **`POST /api/management/push/register`** (User-auth) to upsert on
  app launch. **(Namespace consistency — Codex #7: must be `/api/management/*`, matching §4.4; the
  earlier `/api/push/register` mention was a typo and is void.)**
- **Sender + two-phase lifecycle (Codex #7, correct):** `app/Services/ExpoPushService.php` POSTs to
  `https://exp.host/--/api/v2/push/send` in batches. Expo returns **ticket IDs synchronously**;
  delivery status (**receipts**) is fetched **later, asynchronously**. So we must persist ticket IDs
  (e.g. `push_receipts` table or a column) and run a follow-up job that queries receipts and prunes
  tokens returning `DeviceNotRegistered`. Receipt handling is NOT derivable from `push_tokens` alone.
- **Privacy:** push payloads must **not** leak task content on the lock screen — send a generic title
  ("Neue Aufgabe vom Veranstalter") and resolve the detail only after the app opens authenticated.
- **Opt-out / permissions:** respect OS notification permission; provide an in-app opt-out; never
  assume a token means consent to notify.
- **Trigger:** when **any actor with `assignNote`** (event_admin ∪ owner ∪ superadmin, §4.2 — not
  just "owner", round-6b #3) creates or updates a note's `assignee_user_id`, dispatch a queued job
  `NotifyAssignedNote` → push to the assignee's tokens. Otherwise Event-Admin assignments would fire
  no notification. Also a reasonable place for a future digest.
- **Config:** `config/services.php` expo block; `.env.example` entry. Add `exp.host` to any outbound
  allowlist and document as a **sub-processor** (§10 — must be done **before** production push).

---

## 6. Mobile app (separate Expo repo)

> Tracked here for scope; implemented in the mobile repo. Backend contracts (§4.4, §5, §6.1) live here.

### 6.1 Management login (new auth path)

- Today the app authenticates **Guests** via QR → Sanctum token (Guest is tokenable, guard `web`).
- **Decision: managers do NOT reuse the guest QR path.** A manager processes _all_ guests' personal
  data (list, RSVP, contact) and can delete data — a static/reusable QR is a bearer credential with
  too high a blast radius for that (DSGVO Art. 32; loss of per-person accountability). Guest QR stays
  because a guest only touches their own low-stakes data.
- **`User` cannot mint Sanctum tokens today (Codex #3, verified):** `app/Models/User.php:13` uses only
  `HasFactory, Notifiable` — **no `HasApiTokens`** (only `Guest` has it, `Guest.php:13`). Adding the
  trait is a required P4 building block, not a "confirm".
- Add **User** (owner/manager) login: `POST /api/auth/login` (email + password → Sanctum token). Must
  enforce: **email verified** (`User implements MustVerifyEmail`) + **approved** (`is_approved`) +
  **rate limiting** (`throttle`) like the guest routes. Store the token via `expo-secure-store`.
- **OAuth users have no password** (Google login via `SocialLoginController`). For them, plain
  password login is impossible — the **pairing QR below is their path** (or a "set password" flow).
  This is why the pairing QR is the _recommended_ onboarding, not just a nicety.
- **Actor-type isolation (own finding):** both `Guest` and `User` are `HasApiTokens` under the same
  `sanctum` guard, so `$request->user()` may resolve to _either_. `EnsureGuestHasAppAccess` assumes a
  Guest; a User token hitting a guest route (or vice versa) is a latent bug. Guard both sides:
  guest middleware must reject non-`Guest` tokenables, and `ResolveManagementEvent` must reject
  non-`User` tokenables (assert `instanceof`, and/or use distinct Sanctum token **abilities**).
- **Recommended onboarding — one-time pairing QR (not the guest QR):** owner/admin generates
  a short-lived, single-use pairing QR for a specific invited user (or the user, already logged into
  the web, taps "connect my phone"). Scanning it on the phone **redeems** the token once and mints a
  real User Sanctum session (stored in secure storage). The QR expires and cannot be re-used —
  so it is a _pairing bootstrap_, not a standing bearer credential like the guest QR. Gives guest-like
  UX + proper account security + per-person accountability.
- **Terminology correction (Codex #8, valid):** the minted session is a Sanctum bearer token in
  secure storage — persistent, but **not cryptographically device-bound**. Do NOT call it
  "device-bound". If real binding is later wanted, that means per-device keypair / attestation —
  out of scope now; state it as a known limitation, don't overclaim.
- **Pairing-token hygiene (mandatory — Codex #8):**
    - Store the pairing token **hashed** (`hash('sha256', …)`), never in plaintext — same as Sanctum
      does for its own tokens.
    - **Atomic single-use redemption:** set `redeemed_at` in the same transaction / conditional
      `UPDATE … WHERE redeemed_at IS NULL` that issues the session, so a double-scan can't mint twice.
    - **Short TTL** (e.g. 10 min) + **rate-limit** the redeem endpoint (`throttle`) against brute force.
    - **Never log the token** (no request logs, Sentry breadcrumbs, analytics).
    - **Per-device revocation needs a token reference (Codex round-4 #6):** to revoke a device the
      system must know _which_ Sanctum token to delete. `user_id` + `redeemed_at` + `device_label` are
      not enough. On redemption, store the minted token's id on the pairing row
      (`personal_access_token_id`, nullable FK → `personal_access_tokens`, nullOnDelete). Revoke =
      delete that token (and clear the row). Surface paired devices in `/api/management/me` + web UI.
    - **Management-token abilities (Codex round-4 #6):** mint User tokens with an explicit Sanctum
      **ability** (e.g. `management:*`, or narrower per-role). Guest and User routes then check both the
      tokenable **type** (§6.1 actor-type isolation) **and** the ability — defence in depth so a token
      minted for one surface can't be replayed on the other.
    - Needs a `device_pairings` table (`token_hash`, `user_id`, `expires_at`, `redeemed_at`,
      `device_label`, `personal_access_token_id` nullable FK) + redeem endpoint.
      The backend email/password endpoint remains available, but the native client does not expose it
      alone. Direct native account login is a follow-up that must deliver password and existing OAuth
      providers together; until then, pairing QR is the only organizer onboarding mechanism.
- The app keeps one scanner entry. It distinguishes the fixed token contracts locally (32-char
  Guest invitation vs. 64-char alphanumeric pairing secret) and routes to the matching Guest or
  Organizer area without a separate mode question.
- Organizer session exposes their `accessible_events` + `my_role` per event via
  `GET /api/management/me/events`; the app picks one and sends it as `X-Event-ID` on **every
  subsequent event-scoped request — reads included** (`GET /management/notes`, `GET
/management/photos`, …), the only exemption being `GET /management/me/events` itself. (Round-6 #5 —
  aligns this line with the authoritative active-event contract in §4.4; do not implement the read
  endpoints without the header.)

### 6.2 Screens

- **Notes/ToDos** screen: list own + assigned, create, check off. Backed by §4.4.
- **Photo management**: browse all galleries of the active event and **delete any photo**
  (both roles). Backed by §7.
- Push registration on launch (§5) + notification tap → open the relevant note.

---

## 7. In-app photo deletion from all galleries (both roles)

- Web already supports single/batch delete for organizers. Ensure the delete path is gated by
  `manage` (so managers qualify), not by ownership.
- Add User-auth API under the management namespace: `DELETE /api/management/photos/{id}` + batch,
  gated by `manage` on the event resolved from `X-Event-ID`. **NOT** `/api/photos` — that path is the
  guest's own-photo-only delete (`Api/PhotoController::destroy:113-118`, verified).
- Manager delete works across **all** galleries (app-gallery, presentation, photo-game), unlike the
  guest path which is app-gallery + own-photo only.
- Reuse `PhotoObserver` → Object-Storage blob is removed automatically (GDPR-safe).

---

## 8. "My Events / My Access" overview

- New settings page `pages/settings/Events.vue` at `/settings/events`, in the settings layout
  (next to Profile/Password/Appearance). Lists every accessible event with a **role badge**
  using the canonical set from §3 (**Owner / Event-Admin / Event-Manager / Superadmin**) and a
  quick "switch to" action.
- Also add the role as a badge inside the existing sidebar event-switcher dropdown (cheap win).

---

## 8.1 Refactor: split global user admin from per-event access (`/admin/users`)

**Problem (spotted by André):** `Admin/Users.vue` + `Admin/UserController` mix two unrelated
concerns on one screen:

- **Global eveplan user administration** — the `users.role` (User/Admin) dropdown, delete user.
  This is a platform-owner concern.
- **Per-event access** — `event_access` block: add member by email (`addToEvent`),
  member list, `removeFromEvent`. This is an event-scoped concern.

They read as one thing but are two. The all-events-visibility of admins vs. own/linked-events of
regular users blurs it further.

**Target:** separate them.

- Keep `/admin/users` (or rename to `/admin/accounts`) purely for **global** user management:
  role User/Admin, approval (`is_approved`), delete. No event stuff.
- Move per-event access fully into the **event-scoped** access page (`/event/access`,
  `EventAccessController`) — which after P1 also carries the Owner/Event-Manager role selector.
  Admins reach any event's access page via the event switcher; owners reach their own.
- Net effect: "who is on eveplan / who is superadmin" (platform) is cleanly divorced from
  "who works on this event and in what role" (event).

Slot this into **P1** (it's the same access-UI work) or as an immediate follow-up.

## 9. Naming reconciliation (co_organizer ↔ event_manager)

The codebase uses internal key `co_organizer` (PhotoController, Photos.vue) and label
"Mitveranstalter". The product term is now **Event-Manager**.

**Recommendation:** adopt `event_admin` / `event_manager` as the canonical pivot-role values + policy
vocabulary. Keep the change small:

- Pivot values `event_admin` | `event_manager`, default `'event_manager'`.
- Update `organizer_role` union + `PhotoController.php:54-57` + the `coOrganizer*` locale keys
  (relabel to "Event-Admin"/"Event-Manager", fix the misleading "same access" copy).
- **Photo-badge semantics with co-owners (Codex #7, resolved — snapshot the role at upload):**
  the current logic (`PhotoController.php:54-57`) resolves the badge _dynamically_ as
  `uploader_user_id !== $ownerId ? 'co_organizer' : 'owner'` — which only knows "primary owner vs.
  everyone else", so a **co-owner (Tabea) would wrongly show as "Mitveranstalter"**. Decision: store
  the uploader's **role at upload time** in a snapshot column on `photos`, set on create alongside
  `uploader_user_id`. The badge reads that column, not a live re-derivation. Rationale: auditability
  (history stays true even after the person's role later changes) + correctness for co-owners.
  Backfill existing rows via the same primary-owner heuristic. `guest_id != null` uploads stay
  guest-labelled (no badge).
- **The snapshot union MUST match `roleOn()` exactly (Codex round-4 #5):** `roleOn()` can return
  `superadmin` (a global admin uploading into a foreign event), so the allowed values are
  **`owner | event_admin | event_manager | superadmin`** — the same union in the migration
  (enum/check or validated string), the TS type, the controller write, and the badge UI. Decide the
  _label_ a superadmin upload shows (recommendation: render superadmin as the plain owner/organizer
  badge, no separate public "superadmin" wording — it is an internal role). Do not leave `superadmin`
  out of the column, or global-admin uploads would violate the NOT-NULL/enum constraint.

(Alternative: keep `co_organizer` internally to avoid touching Photos — but then code and product
vocabulary diverge. Not recommended.)

---

## 9.1 Follow-ups (deep linking — not core scope, noted for later)

Both build on **Universal Links (iOS) / App Links (Android)** — requires hosting
`apple-app-site-association` + `.well-known/assetlinks.json` and app claiming the domain (one-time).

- **"Open app, else store" from the QR landing page.** Browsers cannot _detect_ an installed app
  (privacy). But a universal/app link opens the app if present and otherwise falls through to a web
  landing page that reads the user-agent (iOS/Android) and shows the right store button. Achieves the
  desired effect without literal detection. Caveat: in-app webviews (Instagram/FB) don't always
  trigger universal links.
- **"Log in in-app" button under the QR.** A deep link carrying the same token as the QR
  (`https://eveplan.de/app/login?token=…`). App installed → opens app + auto-login; not installed →
  falls back to the PWA. Same payload as the QR (QR = cross-device scan, button = same-device tap).
  Pairs well with the one-time organizer token (single-use / expiring mitigates token-in-URL risk).
  Big UX win for PWA users.

## 10. GDPR / docs sync (governance rule in CLAUDE.md)

- **New sub-processor: Expo push (`exp.host`)** — register in `docs/legal/sub-processors.md`,
  then `resources/legal/privacy.{de,en}.md` §"Drittanbieter", then any Architecture/README mention.
  Follow the CLAUDE.md checklist (grep for the provider name across non-historical docs).
- **New personal data:** notes/todos content, assignee relationships, push tokens → extend
  `UserDataExporter` (Art. 15) so they appear in `/settings/export-data`.
- **Notes retention — full specification, not just "soft deletes" (Codex round-4 #8).** Soft-delete
  (§4.1) + "tied to event end" is not a complete retention spec. P2 must define and ship all of:
    - **Visibility of soft-deleted notes:** by default nobody sees them in the UI; only the Art. 15
      data export includes them (with a `deleted_at` marker) until they are hard-purged — decide and
      document whether the export includes trashed notes (recommendation: yes, until purge).
    - **Hard-purge trigger:** soft-deleted notes are permanently removed after N days
      (`RETENTION_NOTES_DAYS`, new key in `config/retention.php`); _all_ notes of an event are purged
      after event-end + N days, mirroring `app:prune-declined-guests` / `app:prune-invitation-tokens`.
    - **Scheduled command:** `app:prune-notes` (or extend an existing pruner), registered in
      `bootstrap/app.php`/`routes/console.php` like the other retention commands.
    - **Tests:** Pest coverage that the pruner deletes exactly the eligible notes and nothing else.
    - **Published retention text:** if a new retention period is announced to users, update
      `resources/legal/privacy.{de,en}.md` and the `LegalDocumentLoader`-served content so the
      privacy policy matches the actual period (Art. 13 accuracy).
- Retention: push tokens pruned on `DeviceNotRegistered`.
- Update `CLAUDE.md` (data models, roles table, API endpoints) and `docs/ARCHITECTURE.md`.

---

## 11. Phasing (each phase independently shippable)

0. **P0 — Cleanup / multi-tenancy hardening (do FIRST, before any role code).**
   _Decided with André 2026-07-16: clean the dirty controllers before layering a policy on top —
   otherwise the role layer looks "green" while fremde-event data still leaks underneath. Every P0
   item is a bug that exists **today**, independent of the role feature, and is shippable + testable
   on its own (pure cross-event 403 Pest tests, no new roles involved)._
    - **P0.1 — `categories` + `food_specials` become per-event** (Codex #5; round-4 #2: a naive
      "attach all to the default event" is unsafe — these global rows may already be referenced by
      guests of _different_ events via `guests.category_id` and the `guest_food_special` pivot).
        - **Data model — DECIDED (André, 2026-07-16): the `PhotoGameTaskCatalog` delta pattern.**
          `food_specials` is today a **globally seeded, i18n'd template catalog**
          (`2026_03_31_000002_..._seed_catalog.php`, has `translation_key`: Vegetarisch/Vegan/…). We do
          **not** clone it per event. Instead `event_id` is **nullable**: `event_id = null` → global,
          **read-only** template (only superadmin edits; the manager controllers never write null);
          `event_id = <id>` → event-local custom entry. Reads = templates ∪ event-local. **This solves
          "new events have no defaults" for free** — a fresh event immediately sees the templates,
          nothing to copy. The security property still holds: `CategoryController` /
          `FoodSpecialController` writes **always** set `event_id = activeEvent()->id`, so a manager can
          never mutate another event's (or the shared) catalog.
          _(Clone-and-repoint with `NOT NULL` was the considered alternative — rejected: duplicates the
          catalog per event and forces every creation path to seed. Not used.)_
        - **`categories` are `event_id` `NOT NULL` — no global templates (round-6 #2, decided).** Unlike
          food-specials, `categories` ("Badge" labels) are free-form and were never a seeded catalog, so
          `null` has **no** legitimate meaning for them. The migration MUST assign every existing
          category an `event_id` (its referencing event; orphaned/unassignable rows → the default event
          `2026_03_16_130000`) and make the column `NOT NULL`. This prevents a stray legacy category from
          silently becoming a "global" entry visible in every event. So: **`food_specials.event_id`
          nullable (null = template), `categories.event_id` NOT NULL (no template)** — a deliberate,
          documented asymmetry. `GuestController` category validation is therefore the strict
          `where('event_id', $active)` form (no `orWhereNull`).
        - **Migration must be staged, NOT one transaction (round-5 #3):** MariaDB does an **implicit
          commit on `ALTER TABLE`**, so "the whole migration in a transaction" is not achievable once
          columns/FKs are added. Sequence:
            1. Add **nullable** `event_id` (no FK yet).
            2. Backfill in idempotent, re-runnable steps:
                - **`food_specials`:** leave every **seeded template row** `event_id` null (shared
                  read-only template); assign `event_id` only to genuinely event-private custom rows (if a
                  custom row is referenced by guests of >1 event, clone per event and repoint the
                  `guest_food_special` references to the event-local copy).
                - **`categories`:** assign **every** row an `event_id` (its referencing event; orphans →
                  default event); clone-and-repoint `guests.category_id` for any category shared across
                  events. No row may stay null.
            3. **Validation query** (fail loudly): assert no guest references a row whose `event_id` is
               non-null and ≠ the guest's `event_id`; **additionally** assert no `categories` row has a
               null `event_id` (all must be assigned before the NOT NULL step).
            4. Only then add the FK — **`food_specials`:** column stays **nullable** but the FK is
               **`cascadeOnDelete()`, NOT `nullOnDelete()`** (round-6b #1, hard correction): deleting an
               event must **delete** its local rows, not null them. `nullOnDelete()` would turn a private
               local food-special into a **global template** on event deletion — a data/privacy leak
               (possibly private free-text exposed to every event). Cascade drops the local rows; the
               `event_id = null` templates are untouched (no parent to cascade from). **`categories`:** add
               FK **and `NOT NULL`**, `cascadeOnDelete()`.
            5. **Ops:** DB backup + a staging dry-run before prod (this is data surgery).
        - Scope `CategoryController::store` / `FoodSpecialController::store` to `activeEvent()->id`, and
          scope every **read** (`GuestController::edit` loads `FoodSpecial::all()` at `:96` → must become
          "templates ∪ `where event_id = active`"; find all global reads: guest form, table view, guest
          app API).
        - **New-event defaults (round-5 #2) — with the delta model, nothing to seed:** both creation
          paths (`EventController::store:65`, `RequestController::approveEventRequest:157`) keep only
          `createDefaultAlbums` — the null templates are automatically visible to the new event. Just
          confirm `DemoDataSeeder` (`:157` picks random `FoodSpecial` ids) + any E2E seed still resolve
          against "templates ∪ event-local" after the read-scoping change.
    - **P0.2 — `DrinkController::destroy` cross-event guard.** Add
      `abort_if($drink->event_id !== activeEvent()?->id, 403)` (`DrinkController.php:157-162`).
    - **P0.3 — `GuestController::store`/`update` reference validation** (Codex round-4 #2, verified).
      Today `:34,:107` `'group_id' => 'nullable|exists:groups,id'` and `:36,:109`
      `'food_specials.*' => 'exists:food_specials,id'` accept a **foreign event's** group/food-special
      — a cross-event write hole **route-gating does NOT catch**. Change to event-scoped existence
      rules:
        - `groups` have no templates → strict: `Rule::exists('groups','id')->where('event_id', $active)`.
        - **`food_specials` must ALSO accept the global templates** under the delta model (round-6 #1 —
          a plain `where('event_id', $active)` would wrongly reject `event_id = null` templates that are
          valid for every event). Rule: **`event_id IS NULL` OR `event_id = $active`**, e.g.
          `Rule::exists('food_specials','id')->where(fn ($q) => $q->where('event_id', $active)->orWhereNull('event_id'))`.
          Private rows of _other_ events stay rejected. **`categories` use the strict form** (no
          `orWhereNull` — they have no templates, `event_id` is NOT NULL, see P0.1).
        - Add Pest tests: a foreign group*id is rejected; a foreign event's private food-special is
          rejected; a global template (`event_id = null`) is **accepted**.
          *(This replaces the previously-planned P0.3 EventAccessController interim — see P1.)\_
    - **P0.4 — Photo-uploader role snapshot** (Codex #7). Add `photos.uploader_role` column + set it
      on upload + backfill; badge reads the column. Include `superadmin` in the allowed values (§9).
    - **P0.5 — User-deletion is a data-loss bug today (Codex round-4 #3, verified — SEVERE).**
      `events.user_id` FK is **`ON DELETE CASCADE`** (confirmed via `SHOW CREATE TABLE events`), and
      `Admin/UserController::destroy` (`:60-67`) only blocks self-deletion. So a superadmin deleting a
      user who is a **primary owner** silently **cascade-deletes that user's entire event(s)** — every
      guest, photo, drink log — and strips any **co-owner** (e.g. Tabea) of the event, even though she
      is an equal owner. This is broken _before_ the role feature and must be fixed in P0:
        - Block deleting a user who is the **primary owner (`event.user_id`) of any event** unless a
          replacement owner has been promoted to primary first (ownership transfer, §2.2).
        - Detach all pivot memberships of the deleted user (they're already `event_user` rows).
        - Notes → anonymize/null per §4.1.
        - **Never delete yourself** (already enforced) + **never remove the last superadmin** of the
          platform (guard against accidental admin-lessness).
        - **Change the `events.user_id` FK to `RESTRICT`/`NO ACTION` — explicitly, not "re-evaluate"**
          (round-5 #4). After the P0 fix a direct user-delete must **never** delete events. Migration
          drops the current `ON DELETE CASCADE` and re-adds the FK as `restrictOnDelete()`. An event is
          then deletable **only** through the explicit event-delete path (`§2.2 deleteEvent`), whose own
          child cascades (guests/photos/…) stay intact and unaffected by this change.
    - **P0 acceptance:** Pest cross-event 403 tests for drinks/categories/food-specials **and foreign
      group_id/food-special on guest create/update**; existing wedding data still visible after the
      backfill; user-deletion blocked while primary-owner; no behavioural change for the single-event
      owner.
    - **P0 docs:** append a `docs/DECISIONS.md` entry (the event_id backfill + clone-and-repoint is
      irreversible-ish data surgery, and the cascade change is a safety decision — record the
      reasoning) and note the schema changes in CLAUDE.md's data-model section.
    - **P0 commit checkpoints — each is one green-CI commit, safe to push, in this order.** Every
      checkpoint leaves the app fully working (they are independent bug-fixes, not a half-built
      feature), so André can stop/commit/push after any one and resume later. "Green CI" = `prettier`
        - `eslint` + `vue-tsc typecheck` + `pest` (against `laravel_test`) all pass — verified via the
          `eventplaner-vite-1` container before the commit message is handed over.
        1. **Checkpoint A — P0.5** (user-deletion / FK `RESTRICT`). Self-contained: migration + guard +
           Pest. No dependency on the others. _(Do first — highest data-loss risk.)_
        2. **Checkpoint B — P0.2** (drink cross-event guard). Tiny, independent. + Pest.
        3. **Checkpoint C — P0.4** (photo `uploader_role` snapshot). Migration + write + backfill + badge
           read. Independent. + Pest.
        4. **Checkpoint D — P0.1** (food_specials/categories delta migration + controller/read scoping).
           The big one. **Must precede E** (E's food-special validation needs the new `event_id`).
           Ships whole: migration + scoped writes + scoped reads together, so no intermediate broken
           state. + Pest.
        5. **Checkpoint E — P0.3** (`GuestController` reference validation). Depends on D. + Pest.
           Order A→B→C→D→E respects the only hard dependency (D before E); A/B/C are interchangeable. Each
           `git commit` maps to one checkpoint, push per your normal `develop → staging → production`
           rhythm. **CI passes at every checkpoint** because none of them half-lands a schema/scoping
           change — the migration + its dependent controller edits are always in the _same_ commit.
1. **P1 — Authorization backbone (web-only, invisible to users).**
   Pivot `role` + backfill, `EventPolicy`, split route groups **with the per-route corrections
   (§2.3)**, **full cross-event controller audit + fixes (§2.4a)**, game-toggle extraction, Inertia
   `my_role`, sidebar/page gating, `Event/Access.vue` copy + role display, `/admin/users` split
   (§8.1). _Highest value, lowest risk. Ships the actual "manager can't administer" behaviour._
   **Ships WITH its docs (Codex #9):** the role/data-model change lands in the **same P1** —
   CLAUDE.md (roles table, `event_user.role`, `EventPolicy`), `docs/ARCHITECTURE.md` (authorization
   section), and a `docs/DECISIONS.md` entry (co-ownership model + tier semantics). Do **not** defer
   this to P7 — a role model that isn't documented at merge time drifts immediately.
2. **P2 — Notes & ToDos (web).** Model + integrity rules (§4.1), web UI, owner→manager assignment
   (no push yet). **Ships WITH:** `UserDataExporter` extension for notes + the **full notes retention
   spec** (§10: `RETENTION_NOTES_DAYS`, `app:prune-notes`, soft-delete visibility, tests, privacy
   text) — GDPR must land the moment the data is stored.
3. **P3 — "My Events" overview + switcher role badges.**
4. **P4 — Management API + auth.** `HasApiTokens` on `User` + verified/approved/throttle gating,
   actor-type isolation, `POST /api/auth/login`, pairing-QR (`device_pairings`),
   `GET /api/management/me/events` + `X-Event-ID` middleware, notes API, photo-delete API — all under
   `/api/management/*`. _The three "Codex blockers" live here; none block P1._
5. **P5 — Push infrastructure.** `push_tokens` + ticket/receipt lifecycle, ExpoPushService,
   `NotifyAssignedNote` job. **Blocked on:** Expo sub-processor documentation done first (§10).
6. **P6 — Mobile app (Expo repo).** Organizer login, notes screen, photo management, push register.
7. **P7 — Docs finalize.** README stack table + any cross-cutting polish. The role/data-model docs
   (P1), notes GDPR (P2) and push sub-processor (P5) are **already landed with their phases** — P7 is
   only the leftover external-facing overview, not the first time docs get written.

Tests along the way (Pest, against `laravel_test` only — never the dev DB): policy matrix,
route gating per role, **cross-event 403 per mutating controller**, actor-type rejection
(guest token on management route and vice versa), notes authorization (assignee cannot reassign),
photo-delete across galleries, push token upsert + receipt pruning.

**⚠️ Rollout risk (own finding — P1):** existing `event_user` rows are full-access co-organizers
_today_. The `role` backfill to `event_manager` **silently downgrades them** (loses deep settings /
timeline / design / access-mgmt). Before deploying P1: query production `event_user`, check who is
affected (e.g. a spouse/partner who legitimately needs owner-level rights), and decide per row
whether they stay `event_manager` or should be a co-owner. Communicate the change. Also **audit the
existing Pest suite** — any test asserting "co-organizer can open settings" will (correctly) start
failing and must be updated to the new model.

---

## 12. Open detail decisions (non-blocking)

- ~~**Can an Event-Admin grant the Event-Admin tier?**~~ RESOLVED — **no.** Only owners (owner tier,
  which includes co-owners) ∪ superadmin create/revoke event_admins. Event-admins manage only
  event_managers.
- ~~**Owner vs Event-Admin difference:**~~ RESOLVED — Event-Admin does everything `administer`; only
  an Owner (∪ superadmin) can grant co-owner/event-admin, transfer ownership, or delete the event.
- ~~**Co-ownership:**~~ RESOLVED — an event supports **multiple equal owners** (pivot role `owner`);
  the couple are both owners. `event.user_id` = primary/anchor; last-owner protection applies.
- ~~**Primary-owner semantics:**~~ RESOLVED — primary (`event.user_id`) and co-owners are **100%
  identical in capability**; primary is only the anchor that must never be empty (last-owner
  protection). No billing/deletion privilege attached to primary.
- **Naming:** confirm `owner`/`event_admin`/`event_manager` as canonical keys, UI "Event-Admin" /
  "Event-Manager" (§9). Reconcile with existing `co_organizer` string in Photos.
- **Game toggles in settings form (default set):** keep them read/write for owners in the settings
  form **and** add a parallel `manage`-gated toggle on the feature pages (Drinks/PhotoGame) as the
  manager path. Not blocking — implement this way unless André objects.
- ~~**Assigned notes visibility:**~~ RESOLVED — manager sees their own assigned items, labelled
  "Vom Veranstalter für dich erstellt"; not other managers' items.
- ~~**Mobile organizer login:**~~ RESOLVED — real User login (email/password, session persisted),
  NOT the guest QR path; optional one-time bootstrap QR/magic-link for convenience.

---

## 13. Hardening review (round 2, 2026-07-16)

External review (Codex) + a self-review verified against the code. Every claim below was checked
against actual files, not accepted on plausibility.

**Confirmed & folded in (verified at file:line):**

1. API namespace collision — `/api/photos` already guest-owned (`api.php:53-56`) → all management
   endpoints moved to `/api/management/*` (§4.4, §7).
2. No active-event for bearer tokens → `X-Event-ID` + `ResolveManagementEvent` middleware (§4.4).
3. `User` lacks `HasApiTokens` (`User.php:13`) + needs verified/approved/throttle gating (§6.1).
4. Don't blanket-move photo routes — projector-\* + schedule-visibility → `administer` (§2.3).
5. Cross-event guards incomplete → mandatory controller audit; `DrinkController::destroy` fix (§2.4a).
6. Notes integrity rules added (§4.1).
7. Push ticket/receipt lifecycle + lock-screen privacy + opt-out (§5).
8. GDPR distributed into P2/P5, not deferred to P7 (§11).

**Additional findings from the self-review (not raised by Codex):**

- **Global `categories`/`food_specials` tables** — no `event_id` column; rows shared across all
  events. Latent multi-tenancy leak. Flagged as a separate follow-up (§2.4a).
- **Actor-type isolation** — `Guest` and `User` share the `sanctum` guard; middleware on both sides
  must assert the tokenable type (§6.1).
- **OAuth users have no password** → pairing QR is their only login path, which is _why_ it's the
  recommended onboarding, not a nicety (§6.1).
- **Rollout/backfill risk** — the `role` backfill silently downgrades today's full-access
  co-organizers; needs a production `event_user` review + existing-test audit before P1 ships (§11).

**Sequencing verdict:** the three "blockers" (1–3) all gate **P4 (mobile API)**. **None block P1.**
P1 (web role backbone + audit) is the highest-value, immediately-shippable phase and is not held up
by any open item. Pre-P4, items 1–3 must be turned into concrete specs before mobile work starts.

---

## 13.1 Hardening review (round 3, 2026-07-16) — Codex re-review, all 9 verified

Second Codex pass after round-2 changes. Each point re-checked against the code; **all 9 valid**,
folded in. Notable: two were _worse_ than stated.

1. **`EventAccessController` "keep" was wrong** (§2.4a, §11 P0.3). Verified `:21,:43,:70` gate on
   `event.user_id === user->id` → **primary owner OR superadmin only** — even today's pivot
   co-organizers are blocked. Must move to `manageAccess`. **`RequestController` photo-report**
   (`:193-194,:216-217`) same primary-owner-only gate → **decided:** report resolution = `administer`
   (owner ∪ event_admin ∪ superadmin); managers delete gallery photos directly but do not adjudicate
   guest complaints.
2. **Ownership transfer must be atomic + last-owner protection scoped** (§2.2). Transfer = DB
   transaction preserving the outgoing primary as pivot `owner`. Last-owner protection blocks
   demotion/removal/transfer-away, **not** deliberate event deletion by the sole owner.
3. **Notes FK nullability** (§4.1). User deletion is a hard delete → `author_user_id` /
   `assignee_user_id` MUST be `nullable()` + `nullOnDelete()`; added `author_name` snapshot.
4. **`assignNote` disambiguated** (§4.2). `= event_admin ∪ owner ∪ superadmin`; assignee = active
   `event_manager` only. "Admin" = event_admin tier, not global superadmin.
5. **`categories`/`food_specials` promoted from follow-up to P0 blocker** (§11 P0.1). Decided: add
   `event_id` + backfill now, don't defer.
6. **`my_role` canonical set** (§3, §8). `owner | event_admin | event_manager | superadmin` — same
   union everywhere, no more `admin`-shaped drift.
7. **Photo-badge role snapshot** (§9, §11 P0.4). Store `uploader_role` at upload time; stop the
   dynamic owner-vs-else derivation that mislabels co-owners.
8. **Pairing QR not "device-bound"** (§6.1). Term corrected; added token-hygiene requirements
   (hash-at-rest, atomic single-use redeem, short TTL, rate-limit, no-logging, per-device revoke).
9. **P1 docs land with P1** (§11), not P7.

**Verdict:** with P0 (cleanup) prepended and the four pre-P1 items (Codex #1, #2, #5, #6) resolved
above, the plan is implementation-ready. Remaining scoped gates: #3/#4 before P2, #8 before P4.

---

## 13.2 Hardening review (round 4, 2026-07-16) — Codex re-review, all 9 verified

Third Codex pass after round-3 changes. All 9 valid; **two are concrete pre-existing code bugs**
(the guest reference validation and the user-deletion cascade), both promoted into P0.

1. **P0.3 interim would have widened rights** — dropped. Loosening `EventAccessController` to "any
   pivot member" would let every existing co-organizer invite/remove people. Access stays
   owner/superadmin until P1 replaces it with `EventPolicy::manageAccess` (§2.4a, §11).
2. **`categories`/`food_specials` need a _real_ multi-event migration** (§11 P0.1) — verified
   `GuestController.php:34,36,107,109` use unscoped `exists:groups,id` / `exists:food_specials,id`,
   so a **foreign event's** group/food-special is accepted (cross-event write, not caught by route
   gating). Migration must clone-and-repoint shared rows before `event_id` becomes mandatory;
   `GuestController::store/update` must validate references against the active event (new **P0.3**).
3. **User-deletion is a live data-loss bug** (§11 P0.5) — verified `events.user_id` is
   `ON DELETE CASCADE` (`SHOW CREATE TABLE events`) and `Admin/UserController::destroy:60-67` only
   blocks self-delete. Deleting a primary owner nukes their event(s) and strips co-owners. Fix:
   block-while-primary-owner + transfer-first + last-superadmin guard + re-evaluate the cascade.
4. **Demote/remove must be transactional too** (§2.2) — a bare count check races; use
   `lockForUpdate` + re-read inside the transaction, same as transfer.
5. **`uploader_role` snapshot must include `superadmin`** (§9, §11 P0.4) — `roleOn()` returns it;
   union must match across migration, TS, controller, UI (global-admin upload → owner-style badge).
6. **`device_pairings` needs `personal_access_token_id`** (§6.1) — otherwise revoke can't identify
   the bearer to delete; plus explicit `management:*` Sanctum abilities checked alongside actor type.
7. **Push route unified** (§5) — `POST /api/management/push/register`; the stray `/api/push/register`
   was a typo, now void.
8. **Notes retention fully specified** (§10) — soft-delete visibility, `RETENTION_NOTES_DAYS`,
   `app:prune-notes` scheduled command, tests, and published privacy text; not just "soft deletes".
9. **Controller audit is now a per-method checklist** (§2.4a) with object-ID / policy / cross-event
   columns; `schedule-visibility` wording fixed — the **matrix is authoritative** (Event-Admin ∪
   Owner may manage the timeline; Manager may not).

**Verdict:** P0 now absorbs the two concrete pre-existing bugs (guest reference validation,
user-deletion cascade) alongside the multi-tenancy cleanup. Pre-P0/P1 items 1–5 are resolved above;
6–7 gate P4, 8 gates P2/P4/P5. No open blocker remains before starting P0.

---

## 13.3 Hardening review (round 5, 2026-07-16) — Codex re-review, all 5 verified

Fourth Codex pass. Five refinements, all valid; two confirmed against code. Folded in.

1. **Access change needs target context** (§2.2) — `manageAccess(Event)` alone can't tell
   "event_admin invites a manager" (ok) from "event_admin promotes to owner" (forbidden). Added a
   central `changeAccess(actor, event, target, newRole)` checkpoint (policy/service) that all
   invite/role-change/remove flows route through; frontend gating is cosmetic only.
2. **New events need a food-special default after the P0.1 split** (§11 P0.1) — verified
   `food_specials` is a globally _seeded_ i18n catalog and both creation paths
   (`EventController::store:65`, `RequestController::approveEventRequest:157`) seed nothing but
   albums. **Recommended fix: mirror the existing `PhotoGameTaskCatalog` delta pattern** —
   `event_id` nullable, `null` = global read-only template (auto-visible to every event), non-null =
   event-local; manager writes always event-scoped. Solves defaults _and_ isolation without cloning.
   Clone-and-repoint kept as the documented alternative. Demo/E2E seed updated either way.
3. **P0.1 migration can't be one transaction** (§11 P0.1) — MariaDB implicit-commits on
   `ALTER TABLE`. Re-specified as staged: nullable column → idempotent backfill/repoint → validation
   query → FK last; DB backup + staging dry-run.
4. **`events.user_id` FK → explicit `RESTRICT`** (§11 P0.5) — "re-evaluate cascade" was too soft.
   Migration drops `ON DELETE CASCADE`, re-adds `restrictOnDelete()`; events die only via the
   explicit event-delete path, whose child cascades stay intact.
5. **Management tokens must be re-validated per request** (§4.4) — a Sanctum bearer outlives the
   one-time login check. `ResolveManagementEvent` now re-asserts User-type + verified + approved +
   ability + live event membership/role on **every** request; revoke tokens on access loss.
   Precision: `X-Event-ID` is required for **all** event-scoped endpoints incl. the GET reads
   (`/management/notes`, `/management/photos`), exempting only `GET /management/me/events`.

**Verdict:** with rounds 2–5 folded in, the plan is the binding implementation basis. The one open
design choice — food-special/category data model — was **decided by André on 2026-07-16: the
delta/template model** (nullable `event_id`, null = global read-only template), mirroring
`PhotoGameTaskCatalog`. No open blocker remains before starting P0.

---

## 13.4 Hardening review (round 6, 2026-07-16) — post-delta consistency pass, all 5 folded in

Codex pass after the delta model was locked. Five consistency gaps, all valid, all resolved.

1. **P0.3 validation must accept global templates** (§11 P0.3) — under the delta model a food-special
   with `event_id = null` is valid for every event, so a plain `where('event_id', $active)` would
   wrongly reject it. Rule is now `event_id IS NULL OR event_id = $active` for food-specials; foreign
   _private_ rows still rejected. Pest tests updated (template accepted, foreign-private rejected).
2. **`categories` null is disambiguated** (§11 P0.1) — categories were never a seeded catalog, so
   `null` has no meaning for them. **Decided asymmetry: `food_specials.event_id` nullable (template),
   `categories.event_id` NOT NULL (no template)**; the migration assigns every category an event
   (orphans → default event) before the NOT NULL step, so no stray legacy row leaks as "global".
   Category validation uses the strict form (no `orWhereNull`).
3. **Assigned-notes visibility + creator rights** (§4.2, §4.3) — "Assigned to team" is visible to
   `event_admin ∪ owner ∪ superadmin` (not "owners only"), matching who may `assignNote`. Decided:
   the creator/assigner (and any `assignNote` holder) may edit/reassign/delete; the assignee may only
   read + mark done.
4. **Destructive guest ops — conscious matrix decision** (§1, §2.3) — decided with André: reset app
   login + reset drink logs **stay `manage`** (manager owns day-to-day guest + drink-game ops);
   **`DELETE guests/{guest}` is `administer`-only**, gated per-route (it sits among manager-allowed
   guest routes, so `can:administer` on the single route, not a block move).
5. **Stale mobile sentence fixed** (§6.1) — the "X-Event-ID on every write" line now says "every
   event-scoped request, reads included", aligned with the authoritative §4.4 contract.

**Verdict:** no open blocker, no open decision remains. The plan is ready to start P0.

---

## 13.5 Hardening review (round 6b, 2026-07-16) — two hard corrections + two consistency fixes

Post-round-6 pass. **Two are real bugs introduced during the earlier rounds** (self-inflicted, caught
before any code) — both hard corrections; two are consistency completions.

1. **`food_specials` FK must `cascadeOnDelete`, not `nullOnDelete`** (§11 P0.1) — HARD. `nullOnDelete`
   would convert a private local food-special into a **global template** when its event is deleted,
   leaking possibly-private free-text to every event. Cascade deletes the local rows; the `null`
   templates have no parent and stay untouched.
2. **Management middleware must resolve the primary owner via `roleOn()`/`isOwnedBy()`, not a raw
   `event_user` query** (§4.4) — HARD. The primary owner lives in `events.user_id` and is usually
   absent from `event_user`; a pivot-only lookup would lock the primary owner (André) out of the
   mobile API. Check = superadmin ∪ `events.user_id === user.id` ∪ pivot role.
3. **Push fires for any `assignNote` actor, not just owners** (§5) — event_admins may assign (§4.2),
   so the trigger wording/impl is "actor with `assignNote`", else admin assignments notify nobody.
4. **Audit gains a `GuestController::destroy` row** (§2.4a): manager → 403, event_admin/owner → ok,
   foreign guest → 403. Plus a **conscious matrix line for photo reports** (§1): decided —
   **managers do NOT see photo reports**; `RequestController::index` omits them from the manager
   payload, `/requests` stays `manage` only for revocations (§2.3).

**Verdict:** #1 and #2 were genuine correctness/privacy bugs, now fixed. No over-engineering added —
all four are consistency/security closures, not new scope. Plan remains ready for P0.
