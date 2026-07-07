# GDPR Compliance Plan — Overview

## Progress (master tracker)

Tick after pushing each stage — gives you a one-glance view of where you stand.

- [x] **Stage 1** — Imprint + Privacy Policy + footer links → [stage-1-imprint-privacy-policy.md](gdpr/stage-1-imprint-privacy-policy.md)
- [x] **Stage 2** — R2 photo cleanup on user/event deletion → [stage-2-data-deletion-r2-cleanup.md](gdpr/stage-2-data-deletion-r2-cleanup.md)
- [x] **Stage 3** — Data-export endpoint (right of access) → [stage-3-data-export.md](gdpr/stage-3-data-export.md)
- [x] **Stage 4** — Security-header middleware (HSTS, CSP, frame-options) → [stage-4-security-headers.md](gdpr/stage-4-security-headers.md)
- [x] **Stage 5** — Retention policy + scheduled cleanup → [stage-5-retention-policy.md](gdpr/stage-5-retention-policy.md)
- [x] **Stage 6** — Sub-processor documentation (AVV/DPA register) → [stage-6-subprocessor-documentation.md](gdpr/stage-6-subprocessor-documentation.md)
- [ ] **Stage 7** — Cookie consent banner (only when tracking lands) → [stage-7-cookie-consent.md](gdpr/stage-7-cookie-consent.md)

Each stage is **self-contained** — pick any one, tick its acceptance criteria, commit, ship.

---

## Why this plan exists

The app is hosted in Germany (Hetzner), stores photos in Hetzner Object Storage (Nürnberg, since 2026-07-01 — previously Cloudflare R2), sends mail via Resend, and real wedding guests + event owners will sign up. Once a real human creates an account, GDPR (DSGVO) and the German Digital Services Act (DDG) apply in full. Without an imprint, a privacy policy, and a clean data-subject-rights workflow, the project would be **non-shippable** in Germany — fines run from a warning letter (Abmahnung) up to 4 % of annual revenue.

This plan turns the audit findings (see "Today" below) into shippable stages.

## Today (as of 2026-06-30)

Audit baseline — what already works and what is missing:

### ✅ Already in place

- HTTPS forced in production (`AppServiceProvider::boot()`)
- Sessions: database driver, `http_only=true`, `same_site=lax`
- Minimal data collection — no phone numbers, no addresses on guests, no avatars on users
- Account-delete flow with password confirmation (`Settings/ProfileController` + `DeleteUser.vue`)
- Google OAuth only requests the default scopes (id, email, name); no calendar/contacts
- No third-party analytics, no Sentry, no Telescope, no tracking pixels
- No PII in application logs (no `Log::*` calls leak user/guest data)

### ⚠️ Partial

- R2 photo files are not deleted when their owning user/event is deleted — `r2_key` is set to NULL in the DB but the blob stays in the bucket
- No data-export route — users can browse their data but cannot download it
- No retention policy — expired invitation tokens, declined RSVPs etc. stay forever

### ❌ Missing entirely

- **Imprint** (`/impressum`) — legally required in Germany under §5 DDG
- **Privacy Policy** (`/datenschutz` / `/privacy`) — required under GDPR Art. 13 before signup
- **Sub-processor register** (R2 / Resend / Hetzner / Google) — required under GDPR Art. 30 and to make the privacy policy honest
- **Security headers** — no HSTS, no CSP, no X-Frame-Options, no X-Content-Type-Options
- **Footer links** to imprint / privacy policy on the public welcome page and inside the app
- **Cookie consent banner** — not blocking today (only session cookies), but needs to be ready before any analytics or marketing pixel lands

## Guiding principles

- **Legal blockers first, polish later** — stages are ordered by how hard they block going live.
- **End-user pages stay German** — `/impressum`, `/datenschutz` and their content components are written in German because the audience is German-speaking and GDPR transparency requires the user's own language. Dev docs, code comments, and plan files are English (project standard).
- **Self-host content** — no embedded third-party privacy widgets (iubenda, Cookiebot) that would add new sub-processors. Plain Markdown / Vue is enough.
- **No git actions by Claude** — every stage ends at "commit point", André commits + pushes himself.
- **Stay conservative** — if a stage's scope grows mid-implementation, split it. Better six small mergeable stages than one big bang.

## Stage table

| # | Stage | File | Effort | Blocks ship? | Outcome |
|---|---|---|---|---|---|
| 1 | Imprint + Privacy Policy | [stage-1](gdpr/stage-1-imprint-privacy-policy.md) | 2h | yes | Two public pages, linked in footer, signup gated by privacy-acceptance checkbox |
| 2 | R2 cleanup on deletion | [stage-2](gdpr/stage-2-data-deletion-r2-cleanup.md) | 1h | no, but Art. 17 risk | Photos in R2 are removed when their event/user is deleted; orphan-sweep command |
| 3 | Data-export endpoint | [stage-3](gdpr/stage-3-data-export.md) | 1.5h | no, but Art. 15 risk | `/settings/export-data` returns a JSON dump of everything the user owns |
| 4 | Security headers | [stage-4](gdpr/stage-4-security-headers.md) | 0.5h | no | HSTS + frame-deny + CT-options + minimal CSP via middleware |
| 5 | Retention policy | [stage-5](gdpr/stage-5-retention-policy.md) | 1h | no | Scheduled job purges expired invitation tokens, very old declined RSVPs |
| 6 | Sub-processor docs | [stage-6](gdpr/stage-6-subprocessor-documentation.md) | 1h | no, but feeds Stage 1 | `docs/legal/sub-processors.md` with R2 / Resend / Hetzner / Google entries; AVV references collected |
| 7 | Cookie consent banner | [stage-7](gdpr/stage-7-cookie-consent.md) | 1h | only when tracking is added | Plan-only today; full implementation deferred until first non-essential cookie ships |

→ **Total ~7h, plus the ongoing legal-content review on Stages 1 and 6 (lawyer or DSGVO-Generator review recommended).**

## Out of scope (deliberately)

- **Full ISO-27001 or BSI Grundschutz** — overkill for a small wedding/event SaaS, would be months of work for zero practical risk reduction at this scale.
- **Self-hosted email** — Resend stays. Replacing it would add operational load without removing a sub-processor relationship.
- **End-to-end encryption of photos** — photos are inherently sharable with all guests of an event; client-side encryption would break the projector use case.
- **DPIA (Datenschutz-Folgenabschätzung)** — only required for "high-risk" processing (large-scale, profiling, special categories). A wedding photo album does not meet that bar.
- **Real-time consent for analytics** — see Stage 7. The hook is in the plan; the implementation lands once a measurable need (analytics, marketing pixel) appears.

## Follow-up (optional polish, after Stage 6)

1. **DSGVO-Generator / lawyer review** of the German privacy policy text before going public — Stage 1 ships a solid draft, but a final legal pass is recommended.
2. **Automated security-header check** in CI (e.g. `https://securityheaders.com` via curl in a workflow) — keeps Stage 4 honest over time.
3. **Audit log for admin actions** — track when an admin deletes a user / changes a role. Not strictly required, but very useful for incident response under GDPR Art. 33 (breach notification).
4. **English-language versions** of imprint and privacy policy once the app gets non-DE users.
