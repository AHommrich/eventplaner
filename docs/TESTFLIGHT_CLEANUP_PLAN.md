# TestFlight Cleanup Plan — Backend (eventplaner)

Created: 2026-07-19 · Tool-agnostic (executable by Codex or Claude Code).
Companion plan for the app repo: `eventplaner-app/docs/CLEANUP_AND_WISHES_PLAN.md`.

## Why this exists / constraints (read first)

- Device testing on 2026-07-19 surfaced 3 bugs + 1 UX wish. This file covers the
  **backend** part; the app part is in the companion plan.
- **Build economics:** the couple has **exactly one EAS build slot left until the
  1.8. reset**, and the slot is consumed at **`eas build`** time (not at submit).
  So the strategy is: fix everything that needs **no** app build first, verify in
  Expo Go (staging) + an already-installed dev-client, then spend the one build on
  the production TestFlight binary.
- **These two backend items need NO app build** — they deploy server-side and are
  the ideal first step.
- **Backend endpoints differ per client:** Expo Go → `beta.hommrich.app` (staging);
  the TestFlight/production binary → `eveplan.de` (production). Therefore **deploy
  each fix to staging first (verify in Expo Go), then to production** (verify before
  the final TestFlight smoke test), sequentially (never redeploy staging + prod at
  once — VPS RAM; see `CLAUDE.md`).
- Not a store release yet — just a usable TestFlight build.

---

## C1 — Photo report fails on the client although it is saved · P1

**Symptom:** Guest reports a photo → app shows "Meldung konnte nicht gesendet
werden", **but the report + auto-hide land correctly in the DB.**

**Root cause (confirmed in code):** `app/Http/Controllers/Api/PhotoReportController.php`
`store()` persists the `PhotoReport` + `PhotoHide`, then sends the owner mail
**synchronously** (`Mail::to(...)->send(new PhotoReportedMail(...))`, ~line 60-61).
If the mail transport (Resend) hiccups, it **throws AFTER the writes** → HTTP 500 →
the app shows the generic network error even though the report succeeded.

**Fix:**
- Make the mail non-fatal to the response. Preferred: `Mail::to(...)->queue(new
  PhotoReportedMail(...))` (the app already runs `queue:work --stop-when-empty`
  once per minute per `CLAUDE.md`, so it will be delivered). Alternative if queueing
  the Mailable is awkward: wrap the `send()` in `try/catch`, `Log::warning(...)` on
  failure, and still return `201`. The report + hide must never be rolled back by a
  mail failure.
- Keep the `201` JSON response shape (`id`, `status`, `auto_hidden`) unchanged.

**Test:** add/extend a feature test that forces the mailer to throw and asserts the
endpoint still returns `201`, the `PhotoReport` row exists, and the `PhotoHide` row
exists. (Tests run only against `laravel_test` — see `CLAUDE.md`.)

**Verify (manual):** report a photo in the app → success toast; report row + hide
present; mail arrives (or is queued). No build needed.

---

## C2 — RE-DIAGNOSED 2026-07-19: NOT a backend bug (moved to app plan)

**Finding:** `curl` of `https://beta.hommrich.app/api/legal/imprint?locale=de`,
`.../privacy`, and the same on `https://eveplan.de` all return the **correct
sections** (imprint 6, privacy 7, correct headings). So the blank legal screen is
**not** a backend/content/deploy problem — the API is healthy. The bug is app-side:
the legal body text renders in `colors.cardText` on `colors.screenBg`, which is
invisible on themes where `cardText` is light (the couple's olive organizer theme).
Fix moved to the app plan (folded into the contrast work). See
`eventplaner-app/docs/CLEANUP_AND_WISHES_PLAN.md` → C2.

**Minor backend nit (cosmetic, low priority):** the API's `updated_at` comes back as
"now" instead of the file's front-matter date (e.g. imprint `2026-07-08`). That means
`LegalDocumentLoader::splitFrontMatter` is not detecting the front-matter on the
deployed files even though sections parse fine (front-matter lines sit before the
first `## ` and are dropped). Only affects the "last updated" line. Investigate later
(line endings / whitespace on the deployed `.md`); not blocking.

## (Original C2 — kept for reference) Imprint & Privacy screens load blank

**Symptom:** In-app `Impressum` and `Datenschutz` show only "Zuletzt aktualisiert:
<today>" with **no body sections**.

**What we know (from code):**
- App fetches `GET /api/legal/imprint?locale=de` and `/privacy` via `lib/legal.ts`
  and renders `notice.sections[]`. The header date is `notice.updated_at`.
- The screen shows **today's date** + **empty sections**. Parsing the repo file
  would instead yield `updated_at: 2026-07-08` and **6** sections (imprint) / **7**
  (privacy) — `resources/legal/imprint.de.md` etc. are correct in the repo.
- `LegalDocumentLoader` splits sections on `## ` H2 lines and defaults
  `updated_at` to **now** only when the front-matter `updated_at` is missing/
  unparseable. Empty sections ⇒ the served markdown had **no `## ` headings**.
- `Dockerfile.prod` uses `copy . .` and `.dockerignore` excludes `docs` but **not
  `resources`**, so the `.md` files *should* ship. Legal is read at runtime via
  `file_get_contents(resource_path(...))`, so `config:cache`/`view:cache` do not
  affect it.

**So the fix is diagnostic-first** (do NOT assume a single cause):
1. `curl 'https://beta.hommrich.app/api/legal/imprint?locale=de'` and `.../privacy`
   → and the same on `https://eveplan.de/...`. Inspect `updated_at` + `sections`.
   - **If sections are empty on the server** → the deployed image ships an
     empty/stale/older `resources/legal/*.md`, or a parse issue. Confirm the file
     content inside the running container (`resources/legal/imprint.de.md`), fix if
     stale/empty, redeploy (staging → verify → prod).
   - **If the server returns the correct sections** → the blank screen is on the app
     side; see companion plan **C2b** (app must not have been reached / used a bad
     fallback). Re-check against the exact build that showed the bug (Expo Go=staging
     vs TestFlight=prod).
2. After the fix, re-`curl` both environments → expect 6 (imprint) / 7 (privacy)
   sections and the correct `updated_at`.

**Note:** the `Dockerfile.prod` currently has an **uncommitted** change (storage/
bootstrap chown before warmup). Unrelated to legal, but it means the running prod
image may predate recent resource changes — a clean redeploy after confirming file
content is part of the fix.

**No app build needed** for the backend half. The app-side guard (C2b) is a small
JS-only hardening that can ride the one production build.

---

## Deploy discipline (both items)

1. Implement + green tests locally (`composer test`, against `laravel_test`).
2. Hand back an English commit message (`type(scope): summary` + 2 body lines).
   The human commits & pushes.
3. Deploy **staging first** → verify in Expo Go (staging backend) → then production
   → verify via curl + TestFlight smoke. Never redeploy staging + prod together.

## Out of scope here
- App contrast fix (C3), app legal guard (C2b), boot/restore gate (Item 1), and the
  tab-swipe wish (W1) — all in the app companion plan.
