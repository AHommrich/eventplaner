# Event Manager — P4/P5/P6 Hardening Plan

Status: **Checkpoints 0–3 implemented; Checkpoint 4 (build/delivery readiness) OPEN — not test-ready** ·
Created: 2026-07-17 · Author: André + critical review (Codex) + independent verification (Claude).

> **Reality check (2026-07-17, second audit):** unit/Jest suites are green, but this is **not**
> end-to-end test-ready. The companion app's committed lockfile pins SDK-55 packages and `expo-doctor`
> fails (14/18), so no reliable native build exists → push cannot be tested. There is also an
> orphaned-assigned-notes bug and an unverified production scheduler dependency. See **Checkpoint 4**.
> "Test suites green" ≠ "build-ready / verified end-to-end". Do not label this feature "green" until
> Checkpoint 4 clears and André's own build + CI pass.

Follow-up to `docs/EVENT_MANAGER_ROLE_PLAN.md`. P1–P7 are implemented across both repos
(backend `eventplaner`, app `eventplaner-app`), but have **not yet been independently verified**;
a critical re-analysis found real gaps in the **device / push revocation lifecycle** and a few correctness /
legal-accuracy issues. This document is the abarbeitbare (workable) fix plan — usable by any tool
(Claude Code, Codex, OpenCode).

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
- **Problem:** `/api/management/me/pairings` (`routes/api.php:57`) lets any valid management **bearer**
  mint new pairing tokens — a stolen bearer can create extra sessions before being revoked.
  `/api/auth/login` (`app/Http/Controllers/Api/ManagementAuthController.php:35`) mints tokens with **no**
  `DevicePairing`, so password-login sessions never appear in the device list, which only lists
  pairings (`app/Http/Controllers/Settings/DeviceController.php:14`). "Active devices" is therefore not
  a complete session list.
- **Locked fix:** `device_pairings` represents both pending pairing challenges and redeemed credential
  sessions. Every management PAT (password login or pairing redemption) gets a redeemed row; the row
  and its push token reference that PAT with `cascadeOnDelete`. Pending rows have no PAT and are never
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

**Open blockers (Checkpoint 4), independently verified 2026-07-17:**
- ✗ `expo-doctor` **14/18** — committed lockfile pins SDK-55 packages, `react-dom` missing, duplicate
  native modules, `app.json`/`app.config.js` conflict → **no reliable native build → push untestable**.
- ✗ Assigned notes orphaned on member removal/demotion (`EventAccessService`) — not cleaned up.
- ? Production scheduler/queue (`schedule:run` every minute) unverified → push may never be delivered.
- ℹ Push requires a dev/preview build + APNs/FCM credentials (not testable in Expo Go).
