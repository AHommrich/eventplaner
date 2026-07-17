# Event Manager — P4/P5/P6 Hardening Plan

Status: **Checkpoints 0–2 + 4.1/4.2 + 5–7 done (code); 4.3/4.4 ops-open** ·
Created: 2026-07-17 · Author: André + critical review (Codex) + independent verification (Claude).

> **Reality check (updated 2026-07-17):** the hardening code (Checkpoints 1–2, 4.1, 4.2) is in and the
> suites are green — `expo-doctor` is now **17/18** (last check is the `app.json` heuristic
> false-positive), the orphaned-notes bug is fixed. **Still not end-to-end test-ready for push:** the
> two remaining items are **ops** (4.3 production scheduler unverified; 4.4 needs a dev/preview build +
> APNs/FCM). **Checkpoints 5–6 (event-bound QR/theme plus Organizer tabs, gallery and read-only
> schedule) and **Checkpoint 7 (web device/sidebar regrouping) are implemented.** Do not label
> anything "green" for the owner until André's own build + CI pass.

Follow-up to `docs/EVENT_MANAGER_ROLE_PLAN.md`. P1–P7 are implemented across both repos
(backend `eventplaner`, app `eventplaner-app`), but have **not yet been independently verified**;
a critical re-analysis found real gaps in the **device / push revocation lifecycle** and a few correctness /
legal-accuracy issues. This document is the abarbeitbare (workable) fix plan — usable by any tool
(Claude Code, Codex, OpenCode).

## ⚠️ SCOPE FENCE — current build vs. out of scope (read before touching anything)

**IN SCOPE for the active build (build in order):** **Checkpoint 5 → 6 → 7.** These are the event-bound
QR, shared theming, organizer tab rebuild, per-folder photo upload, read-only schedule, and web
sidebar/device-QR regrouping. Everything needed is fully specced below.

**OUT OF SCOPE — do NOT build now (the "Follow-ups" section at the end):**
- **F1 (i18n lint guard)** and **F2 (legal back-link)** are independent polish — schedule them only
  **after** Checkpoints 5–7 land. They touch different files; do not fold them into the current build.
- **F3 (Notes/ToDos → task-centric rework)** is **explicitly deferred (André: "option (2)")**: ship
  the **existing `note|todo` model as-is** in the organizer "Aufgaben" tab (Checkpoint 5.5/6.1). **Do
  NOT** migrate the model, drop the `note` type, or build the ToDo-list rework mid-flight. The rework
  is a clean, separate task after 5–7. Building it now would collide with the tab work in progress.

Boundary rule: if a change is not required by Checkpoint 5, 6, or 7 as written, it belongs in
Follow-ups — leave it there.

## How to work this plan (read first)

- **Read `AGENTS.md` in full** — hard rules + execution environment (test DB + APP_KEY, docker exec,
  Node-20 container for frontend, never commit/push/deploy, hand back an English commit message).
- Each item is tagged **[backend]** (`eventplaner`) or **[app]** (`eventplaner-app`).
- Work in the **checkpoint order** below. Each checkpoint = one independently green, shippable unit
  with its own handed-back commit message. Verify CI green **before** handing back the message:
  - backend: `docker exec laravel-app sh -c 'APP_KEY="base64:WbW7jGEyKUY4O+DvQYyOZ8nteSTHdQ4VDYCcpqJwdHs=" ./vendor/bin/pest'`
    + `docker exec laravel-app ./vendor/bin/pint --test <files>`
  - app: `source ~/.nvm/nvm.sh && nvm use 20 && npm run typecheck && npm test && npm run lint && npm run format:check`
- **Locked context:** actor-type isolation, X-Event-ID contract, policy tiers and cross-event guards
  are correct and must not be regressed — see `docs/EVENT_MANAGER_ROLE_PLAN.md` §2.4a / §4.4 / §6.1.
- The design decisions in the role plan annotated "Codex #N … resolved" stay locked.

## Verified findings (all confirmed against the code)

Independently re-verified 2026-07-17. Line references were correct at time of writing.

---

## Checkpoint 1 — Device / push revocation lifecycle + assigned-note integrity

**Rationale:** "lock a device", "disable push" and "assigned todo" are exactly the flows a tester
will exercise first, and they are currently broken/misleading. Fix these before any meaningful test.

### 1.1 [backend] Tie push tokens to the device so revocation actually stops pushes — RELEASE BLOCKER
- **Problem:** `push_tokens` has only `user_id` (`cascadeOnDelete`) and a unique `expo_token`; it has
  **no** link to the Sanctum token or pairing
  (`database/migrations/2026_07_17_180000_create_push_tokens_table.php`). `DevicePairingController::destroy`
  (`app/Http/Controllers/Api/DevicePairingController.php:92`) deletes the Sanctum token + pairing but
  **not** the push token. `ExpoPushService::sendAssignedNote` pushes to *all* of the user's tokens, so
  a revoked / lost / web-locked device keeps receiving task pushes.
- **Locked fix:** add `personal_access_token_id` (required FK → `personal_access_tokens`,
  `cascadeOnDelete`) to `push_tokens`; set it from the current bearer when a token registers. The
  endpoint rejects a stateful request without a current PAT. Device lock, online logout and
  account/role/access revocation all delete the Sanctum token; the DB cascade then deletes its push
  token(s) atomically. `nullOnDelete` is explicitly not sufficient.
- **Acceptance:** Pest — locking device A deletes device A's push token; a later `sendAssignedNote`
  targets only the remaining device(s); a token registered under one bearer is removed when that
  bearer is revoked.

### 1.2 [backend+app] Real push opt-out + reliable server-side revocation — RELEASE BLOCKER
- **Problem:** `resources/legal/privacy.de.md:27` promises push can be disabled in the app at any time.
  In reality `app/organizer/index.tsx:41` auto-registers push on every organizer focus, and only
  logout sends `enabled=false`. Worse, `lib/sessionStorage.ts:18` deletes the local expo token even
  when an **offline** logout failed to reach the server — the server token then can never be
  deregistered from the app, so pushes can continue after logout.
- **Locked fix [app]:** add an explicit, install-local push preference and toggle to the organizer
  screen. New installations default to off; an existing stored Expo token is treated as legacy opt-in.
  Enabling registers, disabling calls `/push/register` with `enabled:false`, and auto-registration
  only runs while the preference is enabled. Keep the Expo token as installation state rather than
  deleting it with the organizer session.
- **Offline logout invariant [app]:** successful `/auth/logout` relies on the backend PAT cascade.
  If logout cannot reach the server, move the bearer into a dedicated pending-revocation key, clear
  the interactive organizer session, and retry the DELETE on later app starts. Never silently discard
  the only credential capable of revoking the server session.
- **Fix [backend]:** ensure `enabled:false` is idempotent and that account/token revocation paths also
  drop push tokens (see 1.1). Consider a `/push/deregister` that works with just the bearer.
- **Acceptance:** app test — toggle off sends `enabled:false` and stops pushes; offline logout does
  not orphan a server-side token. Backend test — deregister removes the row.

### 1.3 [backend] Assigned entries must be `todo` server-side
- **Problem:** `app/Http/Controllers/NoteController.php:88-99` validates `type in note,todo`
  independently of `assignee_user_id`, so an **assigned `note`** is accepted and fires
  `NotifyAssignedNote` — but the assignee-completion path requires `type === 'todo'`, so the assignee
  can never check it off: a dead assignment. The app only prevents this cosmetically.
- **Fix:** when `assignee_user_id` is set (on create **and** on update/reassignment/type-change), force
  `type = 'todo'` server-side (or reject `note` + assignee with a validation error).
- **Acceptance:** Pest — creating/patching an assigned entry as `note` is coerced to `todo` (or 422);
  an assignee can always complete an assigned entry.

---

## Checkpoint 2 — Session inventory, token TTL, role-change revocation (pre-release)

Tests may run before these, but **do not ship to production without them.**

### 2.1 [backend] Complete, revocable session inventory + hardened pairing creation
> **⚠️ Superseded in part by §5.4 (QR-only login):** password login is being removed, so the
> "password-login sessions" concern below is moot. The remaining, still-valid requirement is: **every
> management PAT has a revocable `device_pairings` session row (PAT + push token cascade), and pairing
> creation is not mintable by a mobile bearer.** With §5.1, every session is a redeemed QR pairing, so
> the inventory is complete by construction. Keep this checkpoint's revocation/cascade guarantees;
> ignore its password-login wording.
- **Problem (original, pre-§5.4):** `/api/management/me/pairings` (`routes/api.php:57`) let any valid
  management **bearer** mint new pairing tokens. `/api/auth/login` minted tokens with **no**
  `DevicePairing`, so password-login sessions never appeared in the device list. "Active devices" was
  therefore not a complete session list.
- **Locked fix:** `device_pairings` represents both pending pairing challenges and redeemed credential
  sessions. Every management PAT gets a redeemed row; the row and its push token reference that PAT
  with `cascadeOnDelete`. Pending rows have no PAT and are never
  shown as active sessions. Restrict pairing creation to the authenticated, CSRF-protected web
  settings route and remove the mobile-bearer creation route entirely. Do not require a password:
  Google-OAuth-only users have an unknown random password and pairing is their only native path.
  Provider-neutral fresh re-auth requires a separate identity-linking/auth slice.
- **Acceptance:** Pest — password-login sessions appear in the device list and are revocable; a mobile
  bearer cannot mint a new pairing; revoking a device drops its Sanctum token **and** push token.

### 2.2 [backend] Management token TTL vs. privacy text
- **Problem:** `config/sanctum.php:50` sets `expiration => null`, so management tokens never expire, but
  `resources/legal/privacy.de.md:43` (+ `.en`) states personal access tokens expire.
- **Locked fix:** add a separate configurable 90-day management-token TTL and pass `expiresAt` on both
  password login and pairing redemption. Keep the existing guest TTL separate. Update both privacy
  locales, retention/config docs and `.env.example` in the same checkpoint.
- **Acceptance:** privacy copy matches reality; if TTL added, a Pest test asserts expiry.

### 2.3 [backend] Role change as a revocation trigger + last-superadmin guard
- **Problem:** `app/Http/Controllers/Admin/UserController.php:37-42` revokes tokens only when a user
  becomes **un-approved**; an admin promote/demote (`role`) leaves existing bearers valid, though the
  docs call role changes a revocation trigger. Also `destroy()` guards the last superadmin but
  `update()` does **not** — the last superadmin can be demoted `admin→user` via update, locking
  everyone out of `/admin/users`.
- **Fix:** revoke the target's API tokens on any role change (not just un-approval); block demoting the
  last remaining superadmin in `update()` (mirror the `destroy()` `<= 1` guard).
- **Acceptance:** Pest — demoting/altering a role revokes tokens; demoting the last superadmin is 4xx.

---

## Checkpoint 3 — Polish (not test-blocking)

- **3.1 [backend+app]** Android: include `channelId: 'organizer-tasks'` in the Expo message payload
  (`app/Services/ExpoPushService.php:27` [backend] sends the payload; the channel is created in
  `lib/managementPush.ts:48` [app]) so Android uses the configured high-importance channel instead of
  the fallback. Add a listener for rare Expo push-token rotation. Rotation must rebind the new token
  and remove the previous token for the current PAT so it cannot cause duplicate delivery. Ref: Expo
  SDK 54 Notifications.
- **3.2 [app]** Stop the guest `BlockedFeaturesContext` from polling `/api/drinks` in organizer mode
  (`lib/BlockedFeaturesContext.tsx:50`); the central client also attaches the management bearer to
  non-management routes (`lib/api.ts:46`), producing 403 noise every ~10s. Gate guest polling off when
  a management session is active.
- **3.3 [backend]** ✅ DONE — `ManagementTokenService::issue` now prefers the web-entered pairing
  label (`$pairing?->device_label ?: $deviceLabel`), so the web field is respected.
- **3.4 [backend]** ✅ DONE — `PruneExpiredDevicePairings` + scheduled `queue:prune-failed`
  (`routes/console.php`) with configurable windows.
- **3.5 [app]** Cold-start redirect race: the global push handler opens `/organizer/notes`
  (`app/_layout.tsx:93`) while the welcome screen replaces to `/organizer` on an existing session
  (`app/index.tsx:86`); depending on timing the push deep-link is lost. Serialize the two.

---

## Checkpoint 4 — Build & delivery readiness (blocks end-to-end testing) — added 2026-07-17

A second audit after Checkpoints 1–3 landed found that the unit/Jest suites are green **but the
companion app cannot produce a reliable native build, so the push flow cannot actually be tested
yet.** Test-suite-green ≠ build-ready. Independently reverified against the code.

### 4.1 [app] Expo SDK dependency integrity — ✅ DONE (2026-07-17, Claude)
- **Resolved:** ran `npx expo install --fix` to reconcile all SDK-55 runtime packages back to SDK 54
  (`@expo/metro-runtime ~6.1.2`, `expo-constants ~18.0.13`, `expo-linear-gradient ~15.0.8`,
  `expo-build-properties ~1.0.10`, `expo-linking ~8.0.12`, `expo-localization ~17.0.9`,
  `expo-splash-screen ~31.0.13`, `@sentry/react-native ~7.2.0`, `react-native-webview 13.15.0`),
  added `react-dom`, de-duplicated native modules, pinned `react-test-renderer` to `19.1.0` (must
  match `react`; a floated `19.2.7` broke `@testing-library/react-native`), regenerated the lockfile.
  `expo-doctor` **17/18**; 308 Jest tests / typecheck / lint / format green.
- **Two deliberate deviations:** (1) `jest-expo`/`eslint-config-expo` stay on `^57` via
  `expo.install.exclude` — the test/lint infra needs them and they are dev-only (downgrading breaks
  the suites). (2) The last expo-doctor check (`app.json` vs `app.config.js`) is a heuristic
  false-positive — `app.config.js` already requires + spreads `app.json`; left as-is because renaming
  touches `.maestro`/EAS and needs a native build to validate. Optional cleanup during 4.4.
- **Original problem (for context):** RELEASE BLOCKER (blocked any native/push test).
- **Problem:** the app is Expo SDK 54 (`expo ~54.0.0`, `react-native 0.81.5`, `react 19.1.0`,
  `expo-notifications ~0.32.17` — all correct), but the **committed `package-lock.json` pins SDK-55
  packages**: `@expo/metro-runtime@55.0.6` (expected ~6.1.2), `expo-constants@55.0.7` (expected
  ~18.0.13), `expo-build-properties@55.0.10`, `expo-linear-gradient@55.0.8`, plus
  `@sentry/react-native@8.17.2` (expected ~7.2.0) and dev-only `jest-expo@57.0.1`. `react-dom` (peer
  of `react-native-web`) is **missing**, and there are **duplicate native modules** (`@expo/metro-runtime`,
  `expo-constants`, `expo-image-loader`). `app.json` and `app.config.js` also conflict. `expo-doctor`
  reports **14/18**. A native/dev build is not guaranteed → remote push cannot be exercised.
- **Fix [app]:** reconcile every dependency to SDK 54 (`npx expo install --check`), add `react-dom`,
  de-duplicate native modules, resolve the `app.json` vs `app.config.js` conflict (pick one config
  source), then **regenerate `package-lock.json`** (delete `node_modules` + lockfile, clean install).
  `@sentry/react-native` must move to the SDK-54-compatible line. Do not merely `.exclude` packages in
  `expo.install` to silence the check.
- **Acceptance:** `npx expo-doctor` passes **18/18**; `npm run typecheck && npm test && npm run lint`
  stay green; the lockfile no longer contains `55.x`/`57.x` runtime entries.

### 4.2 [backend] Assigned notes are orphaned on member removal / demotion — ✅ DONE (2026-07-17, Claude)
- **Resolved:** `EventAccessService::detachAssignedNotes()` sets `assignee_user_id = null` inside the
  `removeMember` and `setMemberRole` (non-manager) transactions, so a removed/promoted user's assigned
  notes revert to the assigner's plain unassigned notes. Tests: `tests/Feature/Event/AssignedNoteCleanupTest.php`
  (remove, promote, no-op re-sync, cross-event isolation). Full suite 450 passed / 1 skipped.
- **Problem (original):** `app/Services/EventAccessService.php` revokes tokens on `removeMember` and on a
  demotion in `setMemberRole`, but does **not** touch that user's assigned notes. A todo assigned to a
  manager who is then removed or promoted stays booked on them: it lingers in the owner's
  "assigned to team" list, points at a non-member, and can never be completed. `NotifyAssignedNote`
  correctly re-checks active membership and stops pushing, so it is data hygiene, not a push leak.
- **Fix:** in the same transaction as the token revocation, clear or reassign that user's assigned
  notes for the event (recommended: set `assignee_user_id = null` so the item reverts to a plain
  unassigned note the owner/admin still sees and can re-assign; alternatively soft-delete). Cover both
  the `removeMember` and the `setMemberRole` demotion path.
- **Acceptance:** Pest — removing or demoting an assignee detaches their assigned notes atomically;
  no assigned note references a non-manager afterwards.

### 4.3 [ops] Verify the production scheduler / queue actually runs — REQUIRED for push
- **Problem:** push delivery depends on the queue draining. `routes/console.php:33` runs
  `queue:work --stop-when-empty` **from the Laravel scheduler**, and the comment states the scheduler
  itself is driven by a host cron `php artisan schedule:run`. If the Coolify production container does
  not run `schedule:run` every minute (or a dedicated worker), `NotifyAssignedNote` is enqueued but
  never processed → no push ever arrives, silently.
- **Fix [ops, André]:** confirm the production deployment runs `schedule:run` every minute (Coolify
  scheduled task / cron sidecar) **or** add a dedicated `queue:work` worker process. Document the
  chosen mechanism in `docs/ARCHITECTURE.md` / the runbook. This is deployment config, not code —
  cannot be verified from the repo.
- **Acceptance:** an assigned note in production produces a processed job + an Expo ticket row.

### 4.4 [ops/app] Push test prerequisites (Expo constraint, not a bug)
- Remote push does **not** work in Expo Go. Testing the real flow needs a **development or preview
  build** with configured **APNs (iOS)** and **FCM (Android)** credentials (EAS credentials).
- **Fix [André]:** produce a dev/preview build (after 4.1) and set up push credentials before the
  end-to-end push test. Ref: Expo push-notifications setup docs.

---

## Checkpoint 0 — Documentation accuracy (do immediately, before Checkpoint 1)

- README + role plan assert "433 passing" / "green" though no verification run belongs to this
  hardening state (`README.md:180`). Mark these as **not-yet-independently-verified** until the full
  post-hardening suites are green. Never preserve a historical green claim across code changes.

---

## Summary for the tester

- **Before you can meaningfully test:** Checkpoint 1 (device/push revocation + assigned-note integrity).
  Testing "lock device" / "disable push" before this gives misleading results.
- **Before production:** Checkpoint 2 (session inventory, TTL/privacy accuracy, role-change revocation).
- **Nice-to-have:** Checkpoint 3.
- **What is already solid (do not re-touch):** server-side actor-type + ability isolation, X-Event-ID +
  policy tiers + cross-event guards, hashed atomic single-use pairing, push payload minimisation
  (no note/guest/event/sender content), Expo 100-msg/1000-receipt limits + DeviceNotRegistered handling,
  Expo SCC documentation, sub-processor register + privacy governance chain.

## Verification result (2026-07-17)

**Test suites green — but NOT build-ready (see Checkpoint 4).**

- Backend: **446 passed, 1 skipped** against `laravel_test`; Pint check green.
- Backend Vue slice: Prettier, ESLint and `vue-tsc --noEmit` green in the Node-20 container.
- Companion app: **308 passed** across 52 Jest suites; TypeScript and Prettier green.
- App ESLint: zero errors; three pre-existing warnings remain in `lib/legal.ts` and
  `lib/monitoring.ts`, outside this hardening slice.

**Checkpoint 4 status (updated 2026-07-17):**
- ✅ 4.1 Expo deps — reconciled to SDK 54, `react-dom` added, deduped, `expo-doctor` **17/18** (last
  check is the `app.json`/`app.config.js` heuristic false-positive).
- ✅ 4.2 Assigned notes orphaned — fixed in `EventAccessService.detachAssignedNotes()`.
- ⏳ 4.3 Production scheduler/queue (`schedule:run` every minute) — **ops, unverified** (André).
- ⏳ 4.4 Push requires a dev/preview build + APNs/FCM credentials — **ops** (André).

---

## Checkpoint 5 — Event-bound devices + organizer theming — ✅ DONE (2026-07-17)

**Product decision (André, 2026-07-17):** this remains **one native app and one design system**.
"One device = one event" describes the organizer session's authorization scope, not a separate app
or a separate visual language. Guest and organizer requests keep their separate auth actors and API
guards, but both surfaces use the same root theme contract, tab chrome, cards, buttons and typography.
Only the tab manifest, data source and permitted actions differ. Today the pairing QR is **user-scoped**
(`device_pairings.user_id` only) and `GET /api/management/me/events` returns *all* of the user's
`accessibleEvents()`, so an owner of many events sees the full switcher. Move to **event-scoped
pairing** without forking the app.

### 5.1 [backend] Event-scoped pairing + token
- Migration: add `event_id` to `device_pairings` as an event FK with `cascadeOnDelete`. It may be
  nullable only during the rollout migration; after legacy sessions are revoked it is required for
  every new management device session.
- Pairing **creation moves to the selected event's access page** (`/event/access`), not the user-level
  `/settings/devices` and not a mobile bearer (aligns with §2.1). It is self-service: every active
  event member may create a QR **only for their own user and the currently selected event**. Do not
  accept a target `user_id`; otherwise an administrator could mint a token that acts and audits as
  another person. Keep membership management and the all-device inventory behind `administer`;
  managers see only their own pairing/device section.
- The QR remains an opaque, random 64-character one-time secret with the existing short TTL. Do not
  embed or trust a client-provided event/role claim: redemption derives user, event and role from the
  hashed, locked `device_pairings` row and rechecks current membership and issuer permissions.
- `DevicePairingController::redeem` / `ManagementTokenService::issue`: mint the token with an
  **event-scoped ability** `management:event:{id}` and persist `event_id` on the session row.
- Update `EnsureManagementUser`, `ResolveManagementEvent` and every management route that does not
  currently pass through `ResolveManagementEvent` (`/me`, `/me/events`, push registration/revocation)
  to accept only the bound `management:event:{id}` ability and to resolve that same device event.
  `X-Event-ID` must equal the bound event when present; a foreign value is 403. Keep the existing
  per-request account/approval/role/tier checks.
- `GET /api/management/me/events` returns **exactly the bound event**, after a fresh membership,
  approval and role check. It must not call unrestricted `accessibleEvents()` for a bound PAT.
- Rollout is fail-closed: revoke/delete all legacy unbound `management:*` PATs and their device/push
  rows before making `event_id` required. Users pair again per event; do not silently infer an event
  for an old multi-event token.
- **Acceptance (Pest):** opaque QR redemption binds the authenticated creator and selected event
  once; a creator cannot mint for another user or a foreign/inactive event; the token ability is
  `management:event:{id}`; `/me`,
  `/me/events`, push and every event endpoint reject a foreign event; membership/approval changes and
  device revoke drop PAT + push token; no usable legacy `management:*` token survives migration.

### 5.2 [backend] Expose the bound event's resolved theme to the management API
- `/api/event/info` stays guest-only, but Guest and Organizer must receive the **same presentation
  contract** from one backend serializer/service. Reuse `ColorRoleResolver` and the existing event
  presentation rules; do not create a second palette mapper for management.
- The management `me` payload for its one bound event returns every value consumed by
  `EventThemeProvider`: raw primary/secondary/tertiary colours; resolved semantic roles
  (`screenBg`, `card`, `cardText`, `cardButton`, `cardButtonText`, `border`, `fab`, `fabIcon`, `navBg`,
  `tabTint`); cover text/shadow colours; `fontFamily`; and `design_preset`/derived theme `variant`.
- Name and JSON shape should be shared with the Guest event-info resource so a future theme field
  cannot be added to one actor and omitted from the other unnoticed.
- **Acceptance (Pest):** for the same event, Guest event-info and the bound management `me` resource
  serialize an identical theme block, including both `classic` and `soft-luxury` presets and resolved
  fallbacks when optional styling fields are absent.

### 5.3 [app] Bind the device to one event + adopt guest theming
- Pairing redeem stores the bound `event_id`; the organizer session pins to it and renders **no event
  switcher or event-selection state**. `GET /api/management/me/events` returning one event is an API
  invariant, not merely a UI convention.
- Extend the app's **existing root** `EventThemeProvider` (`lib/EventThemeContext.tsx`) to choose one
  authenticated source: Guest event-info for a Guest session, or management `me` for a bound
  Organizer session. Do not nest a second provider around `app/organizer/**` and do not keep a static
  maroon/beige Organizer palette.
- Both session types produce the same `EventTheme` object and therefore the same screen gradient,
  `cardSurfaceStyle`, button/FAB/nav colours, font and classic-vs-soft-luxury radii/tab treatment.
  Static app colours remain valid only for semantic states such as error, warning or disabled—not
  for event branding or layout surfaces.
- **Acceptance (Jest):** Guest and Organizer sessions for the same event produce the same theme;
  the Organizer renders no switcher; source changes/logout cannot leak the prior event's theme; both
  presets and missing-field fallbacks are covered; keep `lib/` ≥ 90 % branch coverage.

### 5.4 Auth model — FINAL (André, 2026-07-17): QR-only, per-event
- **The app login stays QR-only — no password login.** The QR moves to **event level**: each event
  has its **own QR** (generated on that event's access page for a specific member). Scanning it logs
  the organizer into **that one event** on that device. Token is event-bound (`management:event:{id}`),
  **no switcher** — one device = one event.
- **No password login in the app** → remove `/api/auth/login`, `ManagementAuthController`, its tests,
  request types and documentation in the same checkpoint. Do not leave a dormant user-scoped token
  path. This also **resolves the OAuth-owner edge case**: nobody needs a password; everyone scans the
  event QR.
- **Multi-event switching is deferred (future).** Real need only for planner agencies; normal users
  are together on one event. Do **not** build the in-app switcher now — a bound device shows its one
  event. (If planner-agency multi-event lands later, add a planner-level session then.)

### 5.5 [app] Notes & ToDos — confirm, do not rebuild
- Owner→manager assignment already exists (`EventPolicy::assignNote` + `NoteController`, backed by
  tests). The Organizer tab set is identical for all organizer roles. Inside "Aufgaben",
  owner/event-admin/superadmin may assign todos; an event-manager sees and updates only the entries
  already allowed by the API. Frontend gating is cosmetic; retain the server policy tests. Verify
  `app/organizer/notes.tsx` visibly separates notes vs todos. No backend change expected.

**Checkpoint 5 verification:** backend Pint + full Pest **450 passed / 1 skipped**. App TypeScript,
coverage (**329 tests / 52 suites; `lib/` 90.55% branches**), ESLint (0 errors; 3 locked pre-existing
warnings) and Prettier green. `expo-doctor` remains the locked **17/18** `app.json` heuristic described
in Checkpoint 4.1; its configuration files were deliberately not re-touched without a native build.

---

## Checkpoint 6 — Organizer app UX (navigation, settings, photo parity) (added 2026-07-17)

The organizer surface currently feels rough (a single home screen + two tool buttons). Give it a
proper structure that reuses the guest app's building blocks and the event theme (Checkpoint 5.2/5.3).

### 6.1 [app] Navigation + styling — DECIDED
- Keep one Expo Router app. Extract the current Guest tab chrome (`SoftTabBar`, classic tab-bar
  treatment, `TabBarIcon`/icon mapping and inset behavior) from `app/(tabs)/_layout.tsx` into a shared
  `EventTabShell`/`EventTabBar` used by both route groups. Do not copy those implementations into an
  Organizer-only layout.
- Guest keeps its current/dynamic tab manifest. Every organizer role gets the **same five tabs**:
  **Übersicht, Ablauf, Fotos, Aufgaben, Einstellungen**. Owner/event-admin/manager differences are
  actions inside those screens, never separate navigation or a visually different app.
- All Organizer screens use the existing `useEventTheme()` parameters for screen, card, tab, button,
  FAB, icon, typography and `variant`. Reuse the existing card/button primitives or extract small
  shared presentation components where the Guest implementation is screen-private. Do not introduce
  Organizer-specific brand tokens.
- **Acceptance (Jest):** the exact tab manifest is asserted for owner, event-admin and manager; Guest
  tabs remain unchanged; the same shared tab shell renders Organizer in classic and soft-luxury;
  forbidden actions stay hidden in UI and independently 403 in backend policy tests.

### 6.2 [app] Settings screen = generic guest-style — DECIDED (André, 2026-07-17)
- The organizer app gets **no event-configuration screen**. Its "Einstellungen" mirror the guest
  app's app-settings only: **logout, Impressum, Datenschutzerklärung, DE/EN language toggle**.
  **No account deletion.** Extract/reuse the generic settings rows and existing legal screens, rather
  than mounting the whole Guest settings screen: Guest-only export, erasure, visibility and invitation
  actions must not leak into Organizer. Logout must revoke the bound device session with the existing
  offline-retry behavior and then clear its event theme.

### 6.3 [app+backend] Photo-management tab = administrative gallery — DECIDED (André, 2026-07-17)
> **DONE (Claude backend + Codex app, 2026-07-17):** `POST /api/management/photos` plus the
> Organizer grid/detail/delete/upload UI and folder picker are implemented and tested.
- The "Fotos verwalten" tab is an **administrative version of the guest gallery**: grid + **detail
  view** + delete, across all three albums (`app_gallery`, `presentation`, `photo_game`). Owners and
  managers may upload to `app_gallery` or `presentation`. A generic upload must not create an orphan
  `photo_game` photo without a `PhotoGameAssignment`; upload to that album is available only through
  an assignment-aware flow. The album remains viewable/deletable here.
- **Backend [new]:** add `POST /api/management/photos` (management_event, `manage` tier) taking a
  target `album_id` that must belong to the resolved event, allow only the two generic-upload album
  types above, and reuse the existing MIME/size validation, `PhotoSanitizer` (EXIF strip) and
  object-storage conventions. On a failed DB write, remove the uploaded object. Keep every existing
  cross-event guard and snapshot uploader identity/role consistently with other uploads.
- **App:** extract pure Guest gallery presentation primitives (grid, detail viewer, loading/empty
  states, picker/compression) and keep Guest and management API adapters separate. Organizer never
  receives Guest report/hide controls. The folder picker may view all three albums but offers only
  valid upload targets.
- **Acceptance:** Pest covers both roles, valid albums, foreign-event/foreign-album rejection,
  forbidden `photo_game` generic upload, validation, EXIF sanitization and storage cleanup; Jest covers
  view/detail/delete/upload, role actions and both theme variants; keep `lib/` ≥ 90 % branches.

### 6.4 [app+backend] Timetable — read-only view — DECIDED (André, 2026-07-17)
> **DONE (Claude backend + Codex app, 2026-07-17):** `GET /api/management/schedule` plus the
> shared read-only schedule presentation are implemented and tested; no mutation route exists.
- The organizer app shows a **read-only** timetable (schedule) for the bound event. **All editing
  stays in the web app** — no edit UI, no schedule-mutation calls from the app, so no role-model
  conflict. Reuse the guest schedule display component. Expose the bound event's schedule for reading
  via a dedicated `GET /api/management/schedule` endpoint resolved from the bound PAT/event. The
  Organizer gets the event's complete schedule; do not reuse Guest group filtering in the data adapter.
  Extract/reuse the schedule presentation component only. No management schedule mutation route exists.
- **Acceptance:** Pest proves only the bound event can be read and no mutation route is exposed; Jest
  proves the shared presentation renders the full management data without Guest-group filtering.

### 6.5 [app] Guest gallery folder selection — PREPARE ONLY — DECIDED (André, 2026-07-17)
- **Not shipped to guests now.** Guests keep **only the `app_gallery` folder** for free
  browsing/use. Build the gallery so a folder selector can be added later without a rewrite
  (**photo_game** next, **presentation** after) — structure the components/data for multiple albums,
  but **expose only `app_gallery`** for now. Presentation stays host-curated (view-only, no guest
  upload) when it lands. No guest-visible behavior change in this checkpoint.

**Checkpoint 6 verification:** backend Pint + full Pest **458 passed / 1 skipped / 1522 assertions**.
App TypeScript, coverage (**345 tests / 58 suites; `lib/` 90.81% branches**), ESLint (0 errors; 3
locked pre-existing warnings) and Prettier green. `expo-doctor` remains the locked **17/18**
`app.json` heuristic; the configuration was not re-touched.

---

## Checkpoint 7 — Role model simplification, sidebar regrouping, device-login relocation — ✅ DONE (2026-07-17)

### 7.1 [none] Role model — KEEP AS BUILT (confirmed André, 2026-07-17) — NO CHANGE
- André's model = exactly the P1 three tiers: **root owner** = primary `owner` (`events.user_id`),
  can add `event_admin`s; **event_admin** = a "co-owner" the root adds, owner-like but may **only add
  managers** (never other event_admins); **event_manager** = limited. Superadmin = platform (André).
- This is already implemented in `EventPolicy::changeAccess` (only owner-tier grants `event_admin`;
  `event_admin` grants only `event_manager`). **No code change.** The earlier "simplify to
  owner+manager" idea is dropped. The three tiers are barely visible to end users, which is fine.
- **Selector nuance — RESOLVED (Claude, delegated by André): keep as built.** The access selector
  keeps the full co-owner "Owner" grant option, and any owner-tier (not only the primary) may grant
  `event_admin`. Rationale: Tabea is already a real pivot-`owner` (see `CLAUDE.md`); dropping the
  Owner grant would risk that live co-ownership. A purely cosmetic 3-tier selector is an optional
  later follow-up, not part of this plan.

### 7.2 [web] Relocate device pairing out of user settings — DECIDED (André, 2026-07-17)
- The app's concept is "event assignment via QR login", so the QR belongs to the **event**, not the
  eveplan user settings. Move QR/device generation from `/settings/devices` into the **event access
  page** (`/event/access`). **Remove the `/settings/devices` user-level device section.** The page may
  expose a narrowly scoped self-pairing/device panel to every active member, while its access/role
  editor and event-wide device inventory remain owner/event-admin-gated. A member may revoke their own
  devices; administrators may revoke any device for this event. Show target identity, last-used and
  expiry state, and preserve all Checkpoint 1/2 revoke guarantees. Password login is dropped (§5.4)
  — the per-event self-service QR is the only login.

### 7.3 [web] Sidebar/nav regrouping — DECIDED (André, 2026-07-17)
- Current sidebar mixes event-specific and platform items under one "Plattform" group. Regroup:
  - **Event group** (everyone with event access, tier-gated per item): guests, invitations, drinks,
    photos, notes, plus **event access ("Zugang verwalten")** and **requests/reports ("Meldungen")** —
    these are event-scoped, not platform.
  - **Platform group** (superadmin/André only): global **user management ("User-Verwaltung")**.
  - Keep the existing per-item role gating; this is purely grouping/labeling clarity.

**Checkpoint 7 implementation:** `/event/access` is now available to every active event member for
self-pairing and own-device revocation. Its membership editor and event-wide device inventory remain
`manageAccess`-gated; administrators may revoke any device only inside the active event. The former
`/settings/devices` page/routes were removed. The sidebar now groups event-scoped tools under Event
and leaves only global user management under Platform. The existing three-tier role model was not
changed.

**Checkpoint 7 verification:** backend Pint + full Pest **457 passed / 1 skipped / 1563 assertions**;
web Prettier, ESLint and vue-tsc green. The unchanged app remains green with TypeScript, coverage
(**345 tests / 58 suites; `lib/` 90.81% branches**), ESLint (0 errors; 3 locked pre-existing warnings)
and Prettier. `expo-doctor` remains the locked **17/18** `app.json` heuristic; its configuration was
not re-touched.

---

## Follow-ups (post Checkpoint 5–7, captured 2026-07-17 — not blocking the current build)

### F1 [web+app] Enforce i18n — catch hardcoded user-facing strings
- **Problem (André):** hardcoded texts keep slipping in that the translation layer (`vue-i18n` web /
  `i18n-js` app) never sees, so they can't be translated and drift silently.
- **Fix:** add an automated guard, not just manual review. Web: an ESLint rule against bare string
  literals in Vue templates (e.g. `@intlify/vue-i18n/no-raw-text`) + a CI step. App: an equivalent
  lint/test that flags literal user-facing strings in JSX outside `t(...)`. Wire both into the existing
  CI gates. Also sweep + fix the current offenders the rule surfaces.
- **Acceptance:** the lint fails on a newly introduced raw user-facing string in both repos; existing
  offenders fixed; DE/EN key parity check stays green.

### F2 [web] Legal pages "back" link goes home instead of to the referrer
- **Problem (André):** on `/impressum` and `/datenschutz` the top-left back link returns to the home
  page, not to where the user came from. Check whether this is a general pattern (other sub-pages too).
- **Fix:** make the back affordance return to the previous location (history back / stored referrer)
  and fall back to home only when there is no in-app history. Verify it does not break deep-link/direct
  entry. Note: guest app has its own legal screens — check the same there if applicable.

### F3 [backend+app] Notes & ToDos rework — ToDo-first; notes concept in question — ⚠️ DECISION PENDING
- **Context:** today it is a **single `Note` model** with `type` (`note`|`todo`) + `is_done` +
  `assignee_user_id` + `body`. A "note" is effectively a todo without a checkbox, so the two types
  feel redundant (André: "zwei identische Models, gleicher Wert").
- **Wanted:** a **solid ToDo list** as a reminder aid — per owner/manager, shows what's still open,
  checkable, with standard quality-of-life (open/done filter, sensible sort, "assigned to me" section;
  optional due date). Owner→manager assignment stays (already built).
- **Direction — DECIDED (André + competitor best-practice, 2026-07-17): Option A, task-centric.**
  Collapse to a **single ToDo list**; every item is a checkable task with title + optional free-text
  **`body` (the former "note" content survives here)** + assignee + QoL (open/done filter, sort,
  "assigned to me", optional due date). Drop the separate `note` type from the UI; migrate existing
  `type='note'` rows to todos (kept unchecked) — do not lose their `body`. This mirrors how
  task-centric tools (Todoist/Asana/Things/Notion) attach the note as a task description rather than
  maintaining a second note entity. Note-first apps (Apple Notes/Keep) embed checklists in notes —
  rejected: worse for "who does what / what's open" team coordination.
- **⚠️ Timing — DECIDED (André): option (2).** Codex ships the current note|todo model as-is for now;
  **F3 is a clean rework afterwards**, not built speculatively mid-flight. Sequence F3 after
  Checkpoints 5–7 land.
