# UGC Moderation Plan — Apple Guideline 1.2

**Status:** implemented, awaiting local test run + review
**Owner:** André
**Scope:** Backend repo. App-side work happens in the separate mobile repo after backend endpoints are live.

## Purpose

The mobile companion app displays user-generated photos from the `app_gallery`
album per event. Apple App Store Review Guideline 1.2 requires a report
mechanism, a way for users to hide content from other users, and reactive
moderation by a responsible party. This plan implements the minimum viable
version of that for a private wedding companion, not a public social network.

## How to work with this file

**Read this section every session before touching moderation code.**

- This file is the single source of truth for the moderation work. Update it in
  the same commit that changes code, so it never lags behind reality.
- Progress lives in [Progress Log](#progress-log). Move items from `Open` to
  `Done` there, and add a dated line to [Change Log](#change-log). Do not delete
  history.
- When you finish a working chunk that is ready to commit, output an English
  3-line commit message in the existing project style:
  ```
  type(scope): summary under 72 chars

  Optional body line explaining the why or a non-obvious detail.
  Second body line if the change touches more than one concern.
  ```
  Examples of `type`: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`.
  Examples of `scope` here: `moderation`, `photos`, `requests`, `legal`.
- Do not commit or push. The human reviews and commits. Skip hooks is
  forbidden.
- Doc + code comments in English. Conversation with the human stays German.
- Tests run only against `laravel_test` via the `mysql_testing` connection —
  never against dev DB. `composer test` is the safe entrypoint.

## Design decisions (locked in — do not relitigate)

- **Specific model, not polymorphic.** `photo_reports` has real FKs. If a
  second report kind appears later, it becomes its own table and joins the
  same `/requests` UI hub. Rationale: no concrete second case exists today,
  polymorphic FKs would defeat cascade deletes and force denormalised
  `event_id`.
- **Owner-uploaded photos are reportable.** Rationale: right of publicity /
  GDPR Art. 17 scenario — a guest who was photographed by the host without
  active consent must have an in-app path to raise the issue.
  `photo_reports.reported_guest_id` is nullable and stays null in this case.
- **Owner-uploaded photos are NOT hideable.** Rationale: the host is the
  operator of the event instance, not a peer. Hide operates on
  `guest_content_hides.hidden_guest_id → guests.id` only, so owner uploads
  fall out structurally.
- **App currently shows only `app_gallery`.** Photo-Game and Presentation
  albums are not visible to guests in the app. Moderation is scoped to what
  the app renders. See [Photo-Game readiness](#photo-game-readiness).
- **No new admin dashboard.** Reports plug into the existing `/requests` tab
  next to revocations and event requests. Sysadmin visibility follows the
  same `isAdmin()` branch that `EventRequest` already uses in
  `RequestController::index`.
- **Reporter identity is never exposed to the reported uploader.** Enforce
  at API level.
- **Auto-hide on report.** The reported photo becomes invisible to the
  reporter immediately, without waiting for resolution. Apple requirement.

## Data model

### `photo_reports`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `event_id` | fk `events.id` | denormalised, cascade on delete, enables cheap owner-scope queries |
| `photo_id` | fk `photos.id` | cascade on delete |
| `reporter_guest_id` | fk `guests.id` | cascade on delete |
| `reported_guest_id` | fk `guests.id` nullable | set null on delete; null for owner uploads |
| `reason` | enum('inappropriate_content','privacy','other') | required |
| `message` | text nullable | max 1000 chars |
| `status` | enum('open','resolved') default 'open' | |
| `resolved_at` | timestamp nullable | |
| `resolved_by_user_id` | fk `users.id` nullable | set null on delete |
| `created_at`, `updated_at` | timestamps | |

### `photo_hides`

Single-photo hides. Populated automatically on report, or (future) via a
dedicated hide-photo endpoint.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `viewer_guest_id` | fk `guests.id` | cascade on delete |
| `photo_id` | fk `photos.id` | cascade on delete |
| `created_at`, `updated_at` | timestamps | |
| unique | (`viewer_guest_id`, `photo_id`) | |

### `guest_content_hides`

Hide all photos of a specific guest for the viewer.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `event_id` | fk `events.id` | denormalised, cascade on delete |
| `viewer_guest_id` | fk `guests.id` | cascade on delete |
| `hidden_guest_id` | fk `guests.id` | cascade on delete |
| `created_at`, `updated_at` | timestamps | |
| unique | (`viewer_guest_id`, `hidden_guest_id`) | |

## API

### `POST /api/photos/{photo}/report`

**Auth:** Guest/Sanctum.
**Rate limit:** `throttle:10,60` per authenticated guest.

**Payload:**
```json
{
  "reason": "inappropriate_content|privacy|other",
  "message": "optional string, max 1000"
}
```

**Response 201:**
```json
{ "id": 42, "status": "open", "auto_hidden": true }
```

**Validation:**
- Photo must be visible to the calling guest (same event, not already hidden).
- Photo must belong to the same event as the guest.
- `reason` required, must match enum.
- `message` nullable string, max 1000.
- Owner uploads (`photo.guest_id IS NULL`, `uploader_user_id` set) are allowed
  — `reported_guest_id` stays null.

**Side effect:** A `photo_hides` row is created for `(reporter, photo)` so the
photo disappears from the reporter's gallery immediately.

**Notification:** Send mail to `event.user` (event owner) — no reporter name in
the mail, only photo thumbnail URL, reason, message.

### `POST /api/guests/{guest}/hide-content`

**Auth:** Guest/Sanctum.

**Response 201:**
```json
{ "hidden_guest_id": 9 }
```

**Validation:**
- Viewer and hidden guest must share the same event.
- Cannot hide self.
- Idempotent — creating an already-existing hide returns 200 with the same shape.

### `DELETE /api/guests/{guest}/hide-content`

**Auth:** Guest/Sanctum.
**Response:** 204 (idempotent — 204 even if there was nothing to delete).

### `GET /api/guests/hidden-content`

**Auth:** Guest/Sanctum.

**Response 200:**
```json
{
  "hidden_guests": [
    { "id": 9, "firstname": "Bob", "lastname": "K." }
  ]
}
```

### `GET /api/photos` — filtering behaviour (existing endpoint)

Server-side filters applied for the calling guest:

1. Photos with `guest_id` in `guest_content_hides.hidden_guest_id` for this
   viewer are excluded.
2. Photos in `photo_hides` for this viewer are excluded.
3. Owner uploads (`guest_id IS NULL`) are always visible — they cannot be
   hidden by guests.

Response items must expose `guest_id` (nullable) so the app can decide whether
to show the hide button.

**Forward compatibility:** when the app later gains an album switcher, an
optional `?album=<slug>` query param will filter by `photo_albums.slug`.
Default (no param) stays `app_gallery`. Not in scope for this iteration.

## Backend UI integration

Reports land in the existing `/requests` tab. `RequestController::index` gains
a new `photo_reports` collection alongside `revocations` and `event_requests`.

**Scope by role:**
- Event owner: reports where `event_id = active_event_id` and `status = 'open'`.
- Sysadmin (`isAdmin()`): reports across all events, `status = 'open'`.

**Item shape in the `/requests` response:**
```json
{
  "id": 42,
  "type": "photo_report",
  "event_id": 7,
  "event_name": "Hochzeit A & T",
  "photo": {
    "id": 128,
    "url": "https://.../128.jpg",
    "album_slug": "app_gallery"
  },
  "reporter": { "id": 5, "firstname": "Anna", "lastname": "M." },
  "reported_uploader": { "id": 9, "firstname": "Bob", "lastname": "K." },
  "reason": "inappropriate_content",
  "message": "…",
  "created_at": "2026-07-08T…"
}
```

`reported_uploader` is `null` for owner uploads.
`event_name` is included only when the caller is sysadmin viewing across events.

**Resolve action:**
`POST /requests/photo-reports/{report}/resolve` sets `status = 'resolved'`,
`resolved_at = now()`, `resolved_by_user_id = auth.user.id`.

Guarded by: caller must be sysadmin OR event owner of the report's event.

**No photo deletion from the report UI.** Owner already has delete in
`/photos`, no UI duplication.

## Retention

Add to `config/retention.php`:
```php
'photo_reports_after_event_days' => (int) env('RETENTION_PHOTO_REPORTS_DAYS', 180),
```

New console command `app:prune-photo-reports` (schedule daily in
`bootstrap/app.php`), analogous to `app:prune-declined-guests`. Deletes
`photo_reports` rows whose `event.date + N days < now()`.

`photo_hides` and `guest_content_hides` do not need their own pruner —
`onDelete cascade` on guest and photo FKs cleans them up when the parent row
is removed.

## GDPR docs

Update in the same PR:
- `resources/legal/privacy.de.md` and `privacy.en.md`
  - New data category "Meldungen und Ausblendungen" (`reports and hides`):
    reporter, reported guest, photo ID, reason, message.
  - Purpose: safe operation and enabling right to object (Art. 21 GDPR).
  - Retention: `{{retention.photo_reports_days}}` days after event end.
  - Legal basis: Art. 6 (1) (f) — legitimate interest.
- Add the placeholder `{{retention.photo_reports_days}}` to
  `LegalDocumentLoader::interpolatePlaceholders`.
- `docs/legal/sub-processors.md` unchanged (no new provider).

## Tests

**Feature (`tests/Feature/Api/`):**
- Guest can report a visible photo in own event.
- Report on photo of other event is rejected (404 or 403).
- Owner upload (`guest_id=null`) is reportable and stores `reported_guest_id=null`.
- Report creates a `photo_hides` row for the reporter.
- Rate limit triggers after 10 reports in 60s.
- Guest can hide another guest in the same event.
- Self-hide rejected (422).
- Hide across events rejected (422).
- DELETE `hide-content` is idempotent (204 both first and second call).
- `GET /api/photos` server-side excludes hidden guests' uploads.
- `GET /api/photos` server-side excludes individually hidden photos.
- `GET /api/guests/hidden-content` returns the blocklist.
- Reporter identity is not exposed via any API surface accessible to the
  reported uploader.

**Feature (`tests/Feature/Requests/` or `tests/Feature/Legal/` neighbour):**
- Event owner sees own event's photo reports in `/requests`.
- Owner of another event does not see them.
- Sysadmin sees reports across all events.
- Resolve sets `status`, `resolved_at`, `resolved_by_user_id`.
- Owner of unrelated event cannot resolve (403).
- Item includes `reported_uploader: null` for owner uploads.

**Unit (`tests/Unit/Services/`):**
- `LegalDocumentLoader` interpolates `{{retention.photo_reports_days}}`.

## Photo-Game readiness

Report and hide operate on `photo_id` and `guest_id` respectively — album is
irrelevant at the moderation layer. When the app later gains an album
switcher and shows Photo-Game submissions, moderation works automatically.
The only extension needed will be:

- `GET /api/photos?album=photo_game` support (add query param handling).
- App-side UI (out of scope here).

Presentation album stays owner-only and out of scope.

## App handoff — what the mobile repo needs to build

Deliver back to the app-side session:

- Final endpoint paths, payloads, response shapes, rate limits, auth.
- `GET /api/photos` field list, including nullable `guest_id` so the app can
  conditionally show the hide button per photo.
- Server-side filters that already apply (hidden guests, hidden photos).
- Query params supported now and reserved for later (`?album=` future).
- `GET /api/guests/hidden-content` response shape for the app's settings screen.
- Auto-hide behaviour: report returns `auto_hidden: true`, app should remove
  the photo from its local list without refetch.
- Anonymity guarantee: no endpoint exposes reporter identity to the reported
  uploader — safe to tell App Store reviewers.
- Where reports are moderated: existing `/requests` tab, no new admin route.
- Which tests are green after the PR.

## Non-goals (explicit)

- No polymorphic `reports` table — future report kinds get their own tables.
- No NSFW auto-classifier.
- No backoffice dashboard, no assignment workflow, no status enum richer than
  `open` / `resolved`.
- No delete-photo action inside the report UI.
- No moderation of Photo-Game or Presentation albums — not visible in the app.
- No recovery/undo for resolved reports.
- No deploy triggered by the coding session. Human reviews and merges.

## Progress log

Move items between sections as they land. Each change also gets a dated line
in the Change Log below.

### Open
- [ ] Run `composer test` locally against `laravel_test` and confirm the new
      moderation suites pass before committing (Docker/OrbStack needs to be up).
- [ ] App-side work (mobile repo): report buttons in the photo detail modal,
      hide-content toggle, hidden-content list in Settings, i18n keys.
- [ ] ToS/EULA document (separate PR): required for Apple App Store submission
      alongside this moderation stack.

### In progress
_(none)_

### Done
- [x] Migrations `photo_reports`, `photo_hides`, `guest_content_hides`
- [x] Models `PhotoReport`, `PhotoHide`, `GuestContentHide`
- [x] `POST /api/photos/{photo}/report` (rate-limited, auto-hide, owner mail)
- [x] `PhotoReportedMail` + blade view (reporter identity omitted)
- [x] `POST /api/guests/{guest}/hide-content`, `DELETE`, `GET`
- [x] `GET /api/photos` filters hidden guests + hidden photos + exposes
      nullable `guest_id`
- [x] Requests hub: `photo_reports` card, sysadmin cross-event view, resolve
      endpoint (`requests.photo-reports.resolve`) + Vue card + i18n DE/EN
- [x] Retention config + `app:prune-photo-reports` weekly schedule
- [x] Privacy DE/EN markdown updated (reports + retention line);
      `{{retention.photo_reports_days}}` placeholder in `LegalDocumentLoader`
- [x] Feature tests: `tests/Feature/Api/PhotoModerationApiTest.php`,
      `tests/Feature/Requests/PhotoReportsTest.php`
- [x] Unit test: `LegalDocumentLoaderTest` covers the new placeholder

## App handoff — actual endpoint contract (final)

Deliver this to the mobile-app session as-is.

### `POST /api/photos/{photo}/report`
- Auth: Sanctum bearer, `EnsureGuestHasAppAccess`, `throttle:10,60`.
- Body: `{ "reason": "inappropriate_content"|"privacy"|"other", "message"?: string(max:1000) }`.
- 201 → `{ "id": number, "status": "open", "auto_hidden": true }`.
- 404 if the photo is not in the caller's event or not in `app_gallery`.
- 422 on validation errors.
- 429 after 10 requests within 60 minutes.
- Side effects: adds a row to `photo_hides` for the reporter (auto-hide) and
  fires an anonymous `PhotoReportedMail` to the event owner.

### `POST /api/guests/{guest}/hide-content`
- Auth: same as above.
- Body: none.
- 201 → `{ "hidden_guest_id": number }` on first create; 200 with the same
  shape on subsequent calls (idempotent).
- 422 on self-hide or cross-event target.

### `DELETE /api/guests/{guest}/hide-content`
- Auth: same as above.
- 204 always (idempotent even when nothing existed).

### `GET /api/guests/hidden-content`
- Auth: same as above.
- 200 → `{ "hidden_guests": [{ "id": number, "firstname": string, "lastname": string }] }`.

### `GET /api/photos` (extended)
- Now returns `guest_id` per row (nullable). App uses `guest_id === null` to
  hide the "hide this uploader" button for owner uploads.
- Server-side filters (transparent to client): rows where `guest_id` is in
  the caller's `guest_content_hides`, and rows in `photo_hides` for the
  caller. Owner uploads always visible.
- No new query params in this iteration. When the album switcher lands, a
  future `?album=<slug>` will be added; the report/hide endpoints already
  operate album-agnostically.

### Anonymity guarantee
- No endpoint exposes reporter identity to the reported uploader. The mail
  to the event owner contains reason, message and photo preview — no
  reporter name.

### Where moderation happens
- Existing `/requests` tab (Inertia page `Requests/Index.vue`). No new admin
  route. Sysadmin sees reports across all events with `event_name`
  populated; event owners only see reports of their active event.

## Change log

_Newest first. One line per change. Format: `YYYY-MM-DD — what — commit sha (fill after commit)`._

- 2026-07-08 — Backend implementation landed: migrations, models, API endpoints, Requests-hub wiring, retention command, privacy docs, tests. Docker was down locally so the suites still need to be run before the commit is finalised. Commit sha TBD.
- 2026-07-08 — Plan drafted and committed. Backend implementation not yet started.
