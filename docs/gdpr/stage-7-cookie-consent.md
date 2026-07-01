# Stage 7 — Cookie consent banner (deferred until tracking lands)

**Effort:** ~1 h when actually built; **0 h now** — this stage is intentionally on hold.
**Outcome (when implemented):** A self-hosted opt-in banner blocks non-essential cookies until the user accepts. Decision persists per user, easily revocable from a "Cookie settings" footer link.
**Why this matters:** Under TTDSG §25, **any** cookie that isn't strictly necessary requires prior consent. As long as the app only sets session cookies + XSRF + locale, no banner is legally required — those are all "strictly necessary". The moment we add an analytics pixel, a marketing tag, embedded YouTube video, or a Hotjar / Plausible-with-cookies instance, the rules change.

## Why we don't ship this today

Auditing what cookies are actually set today:

| Cookie | Source | Purpose | Consent required? |
|---|---|---|---|
| `XSRF-TOKEN` | Laravel | CSRF protection | no — strictly necessary |
| `eveplan_session` (or `laravel_session`) | Laravel | Session storage | no — strictly necessary |
| `locale` in `localStorage` | i18n plugin | Language preference | no — TTDSG §25 (2) Nr. 2 exception for the user's explicit preference |

There is currently **no tracking, no analytics, no third-party widget**. Building a banner today is over-engineering — and worse, a sloppily-built banner that pretends to enforce something it doesn't would be its own GDPR risk.

## When this stage activates

Add a tracking-flavoured dependency? This stage is no longer optional. Triggers:

- Adding `@sentry/vue`, `@posthog/js`, `plausible` (with cookies), Google Analytics, Meta Pixel
- Embedding a YouTube video on the welcome page (sets YT cookies)
- Mounting Hotjar / Microsoft Clarity / any session-replay tool
- Adding Stripe Checkout that drops marketing cookies

When you reach for any of those, **first** revisit this file, then build the banner, then ship the integration. Order matters — once the integration is live without a banner, you have a violation.

## Plan-for-later: what the banner needs to do

Sketch only — flesh out when the trigger fires.

### Behaviour

1. On first visit (no `cookie_consent` cookie/localStorage entry), render a sticky bottom banner with three buttons: `Akzeptieren` (all), `Ablehnen` (only essential), `Einstellungen` (granular toggles per category).
2. Persist the choice with a 6-month expiry — re-prompt after that. Cookie name `cookie_consent`, value JSON like `{"essential": true, "analytics": false, "v": 1}`.
3. Categories should be coarse: `essential` (always on, cannot be turned off), plus a category per actual tracking integration in use (e.g. `analytics`, `marketing`). Never bundle them.
4. The banner does **not** load any third-party JS until consent is given. Plumbing-wise: a Vue plugin that gates `loadAnalytics()` calls behind `consent.analytics === true`.
5. A footer link "Cookie-Einstellungen" reopens the banner — must work from any page including the privacy policy.

### Architecture

- A small composable `useConsent()` exposing the current state and `accept(category)` / `revoke(category)` methods.
- Default reactive store in `resources/js/composables/useConsent.ts`. No external library — keep the sub-processor list short.
- Integrations register themselves in a single file (`resources/js/integrations/analytics.ts` for example) that is **only loaded after consent**.

### Backend

No backend record needed for cookie consent itself (it's a browser-side choice). If you ever want an audit trail, add a `consent_log` table — but that's only useful if a regulator asks.

### Tests (when built)

- Banner appears on first visit
- Choice persists across reloads
- Footer link re-opens the dialog
- No third-party script appears in `document.head` before consent
- Revoking analytics removes the script reference

## Steps (when this stage activates)

1. Confirm which tracker is being added — answer: "what data does it send, and where?"
2. Update `docs/legal/sub-processors.md` (Stage 6) with the new processor
3. Update `resources/js/pages/Legal/Privacy.vue` (Stage 1) to mention it
4. Build the banner per the sketch above
5. Wire the integration **only** behind the consent gate
6. Verify in browser dev tools that no third-party request happens before the user accepts

## File list (placeholder)

| File | Action |
|---|---|
| `resources/js/composables/useConsent.ts` | new |
| `resources/js/components/CookieBanner.vue` | new |
| `resources/js/layouts/AppSidebarLayout.vue` | mount banner globally |
| `resources/js/pages/Welcome.vue` | mount banner globally |
| `resources/js/locales/de.json` + `en.json` | banner strings |
| `tests/frontend/CookieBanner.spec.ts` | new |
| `docs/legal/sub-processors.md` | new processor entry |
| `resources/js/pages/Legal/Privacy.vue` | mention new processor |

## Acceptance criteria (when this stage activates)

- [ ] No non-essential cookie / third-party script appears before the user clicks "Akzeptieren"
- [ ] Revoking consent immediately disables the integration without a page reload
- [ ] Choice persists for 6 months, re-prompts after that
- [ ] Footer link "Cookie-Einstellungen" reopens the banner from any page
- [ ] Sub-processor register and privacy policy both list the new tracker

## Commit suggestion (when this stage ships)

```
feat(consent): cookie banner for <integration name>
```

## Today's deliverable

For now, this stage's only output is **this file**. It documents the trigger conditions so future-you / future-me can't ship analytics by accident without going through the consent gate first.
