# Sub-Processor Register

_Last reviewed: 2026-07-08. Sentry EU covers Laravel backend + Vue web frontend. Helsinki cross-region backup is prepared in code but deferred pending budget._

This register lists every third party that processes personal data on behalf of eveplan. It is the authoritative source the user-facing privacy policy (`/datenschutz`) quotes from. **Any new sub-processor must be added here before the integration ships to production** — otherwise the published privacy policy becomes a lie.

GDPR Art. 28 requires a written data-processing agreement with each processor. Art. 30 requires us to maintain this very list. Signed PDF copies live outside the repository (never commit them) — file paths below point at the local filing location.

---

## Active processors

### Hetzner Online GmbH
- **Purpose:** Application server + managed MariaDB hosting + Object Storage for uploaded photos (S3-compatible API)
- **Data categories:** Everything — user accounts, events, guests, sessions, log files, and photo binaries + object keys. EXIF, IPTC and XMP metadata are stripped server-side via `App\Services\PhotoSanitizer` before any bytes leave the app server. Verified by `tests/Feature/Photo/ExifStrippingTest.php`.
- **Provider address:** Industriestr. 25, 91710 Gunzenhausen, Germany
- **Customer number:** K0847634225
- **Location of processing:** European Union — VPS in Falkenstein (Germany), primary Object Storage in Nürnberg (Germany, bucket `eveplan-photos-prod` at `nbg1.your-objectstorage.com`). Confirmed EU-only by §3 of the AVV. A Helsinki backup bucket is *prepared in code* (see `docs/RUNBOOK.md` §3.2b) but not yet provisioned — update this location entry to name Helsinki once `AWS_BACKUP_*` are set in Coolify.
- **Data Processing Agreement:** Hetzner AVV v1.2, **signed 2026-06-30**. Covers all Hetzner services on the account, including Object Storage.
  - Local filing: `~/legal/eventplaner/dpa-hetzner-2026-06-30.pdf` (EN) + `~/legal/eventplaner/dpa-hetzner-de-2026-06-30.pdf` (DE) + `~/legal/eventplaner/hetzner-tuev-audit-de-2026-06-30.pdf` (TÜV Art. 32 security audit annex).
- **Authorised sub-sub-processors per Annex 3 of the AVV:** Hetzner Finland Oy (EU). The US (Hetzner US LLC, NTT, QTS) and Singapore (Hetzner SG, NTT SG1) entries do not apply because our services are in EU locations — Annex 3 footnote: *„Soweit Sie sich für einen Serverstandort in der EU entschieden haben, werden Ihre Serverdaten ausschließlich innerhalb der EU verarbeitet."*
- **Provider privacy notice:** https://www.hetzner.com/de/legal/privacy-policy

### Resend (Plus Five Five, Inc., d/b/a Resend)
- **Purpose:** Transactional email — verification, password reset, invitations
- **Data categories:** Recipient email address, name (as embedded in mail body), template content
- **Sending domain:** `eveplan.de`, verified via DKIM/SPF (see Resend dashboard).
- **Location of processing:** Ireland (`eu-west-1`). Email content and metadata stay within the EU while the EU sending region is used. Corporate-level Resend infrastructure is in the US, but no user data crosses the Atlantic during normal sending. Fallback to US SCCs stays in the Resend DPA in case Resend ever routes fail-over traffic outside the EU.
- **Data Processing Agreement:** Resend DPA, last updated 2025-12-31
  - Resend states that the DPA "becomes legally binding upon Customer entering into the Agreement" — no separate signature step required for Self-Serve customers.
  - Local filing: `~/legal/eventplaner/dpa-resend-2026-06-30.pdf` (snapshot saved 2026-06-30).
- **Provider privacy notice / source of DPA:** https://resend.com/legal/dpa

### Google LLC (only for "Sign in with Google" users)
- **Purpose:** OAuth login. Triggered only when the user actively clicks the Google button on the login/register screen.
- **Data categories:** Google account id, email address, display name (default OpenID Connect claims only — no calendar, contacts, photos)
- **Location of processing:** United States / global
- **Data Processing Agreement:** Google's standard processor terms for OAuth integrations apply automatically; no separate signed copy required for a SaaS using public OAuth scopes.
- **Provider privacy notice:** https://policies.google.com/privacy

### Functional Software, Inc. d/b/a Sentry
- **Purpose:** Application error monitoring for both the Laravel backend and the Vue web frontend on `eveplan.de` and `beta.hommrich.app`. Backend captures unhandled exceptions, warning-level log records, and a 10 % performance-trace sample. Frontend captures uncaught JavaScript exceptions and a 5 % trace sample. Two Sentry projects share one organisation.
- **Data categories:**
  - *Backend:* Exception messages, stack traces (with argument values via `zend.exception_ignore_args=Off`), request URL, HTTP method, application environment name.
  - *Frontend:* Exception messages, JavaScript stack traces, current URL path, browser + OS strings that Sentry derives from the User-Agent, application environment name.
  - **No personally identifying data by default** — `send_default_pii` defaults to `false` on both the Laravel side (`config/sentry.php`) and the Vue side (`resources/js/app.ts`). This suppresses IP addresses, session cookies, authenticated-user context, and request headers. No browser session-replay is captured. Guest names, email addresses, and photo binaries never travel to Sentry.
- **Location of processing:** European Union — Sentry EU region, Frankfurt (`de.sentry.io`, ingestion endpoint `o4511683645997056.ingest.de.sentry.io`). Region was chosen explicitly at account creation; Sentry does not permit region migration afterwards. Both projects sit in the same EU organisation.
- **Data Processing Agreement:** Sentry Data Processing Addendum v5.1.0 (29 May 2024), self-serve, binding upon account creation. Covers all projects under the organisation.
  - Provider link: https://sentry.io/legal/dpa/
  - Local filing: `~/legal/eventplaner/dpa-sentry-2026-07-05.pdf` (browser-print snapshot of the DPA page, taken 2026-07-07).
- **Free-tier limits enforced:** 5 000 error events/month, 10 000 trace spans/month, 30-day event retention — shared across both projects. `SENTRY_TRACES_SAMPLE_RATE=0.1` (backend) and `VITE_SENTRY_TRACES_SAMPLE_RATE=0.05` (frontend) keep ingest inside the tier.
- **Provider privacy notice:** https://sentry.io/privacy/

### GitHub, Inc.
- **Purpose:** Source-code hosting and CI execution. **No production user data**, but commit metadata is technically personal data of the contributor (commit author name + email).
- **Data categories:** Source code, commit author identity
- **Location of processing:** United States
- **Data Processing Agreement:** GitHub DPA (link only, no per-customer signature for free public repos)
- **Provider privacy notice:** https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement

---

## Inactive / removed processors

### Cloudflare R2 (removed 2026-07-01)
- **Removal reason:** Consolidation onto a single EU-based provider (Hetzner). Photos migrated to Hetzner Object Storage in Nürnberg. See [`hetzner-object-storage-migration.md`](hetzner-object-storage-migration.md) for the migration record.
- **Data returned/deleted:** All ~36 objects copied 1:1 to Hetzner Object Storage; original R2 bucket deleted after 24h stabilisation window. Cloudflare account itself kept only if used for other services (DNS/proxy) — otherwise cancelled.
- **DPA on file:** Cloudflare DPA v6.4 remains in the vault at `~/legal/eventplaner/dpa-cloudflare-2026-06-30.pdf` as historical record.

When a processor is removed (e.g. switching mail providers), keep the historical entry here with the removal date — it helps answer "did you ever share my data with X" requests after the fact.

---

## How to add a new sub-processor

Follow this checklist **before** the integration code goes live:

1. **Document here first.** Add an entry above with purpose, data categories, location of processing, DPA status, and provider's privacy notice link.
2. **Sign the DPA.** Download the signed PDF, file it under `~/legal/eventplaner/dpa-<provider>-<YYYY-MM-DD>.pdf` (or your equivalent vault path).
3. **Update the privacy policy.** Edit `resources/js/pages/Legal/Privacy.vue` section 5 ("Empfänger und Auftragsverarbeiter") so the user-facing list matches.
4. **Then merge the integration code.** Not before. The privacy policy and reality must agree at all times.

If the integration involves cookies or local-storage entries beyond the strictly-necessary set (sessions, CSRF, locale preference), Stage 7 of the GDPR compliance plan ([docs/gdpr/stage-7-cookie-consent.md](../gdpr/stage-7-cookie-consent.md)) activates too — you'll need a consent banner before the new tracker can fire.

---

## Log retention

Where personal data can end up in logs (IP addresses in nginx access logs, request context in Laravel logs), we bound the retention window explicitly.

- **Laravel application log.** `LOG_STACK=daily` + `LOG_DAILY_DAYS=14` (default in `.env.example`). One file per day under `storage/logs/laravel-YYYY-MM-DD.log`; Laravel deletes files older than 14 days on the next log write. `LOG_LEVEL=info` in production so the log doesn't grow with debug chatter.
- **Nginx logs (inside the app container).** `access_log off;` is set at the server-block level in `Dockerfile.prod`, so nginx does not collect any client-IP records for dashboard, login, API or projector requests. The Coolify upstream reverse proxy in front of the app already keeps its own request log for operational purposes, so this is not a monitoring gap. Data-minimisation choice (GDPR Art. 5(1)(c)): removes duplicate PII collection with no distinct purpose.
- **Hetzner-side system logs.** Hetzner has hypervisor-level visibility (VM boot, network abuse reports) but no direct access to the guest filesystem. Their retention is governed by §4 + §7 of the Hetzner AVV (limited-purpose processing, deletion after contract termination). No customer-side action available.
- **Coolify proxy logs.** Coolify runs its own reverse proxy in front of the app container. Its access-log retention is controlled by the Coolify installation config, not by this application. Documented here so we remember it exists.

## Known gaps (tracked for follow-up)

- **Cross-region photo backup — deferred pending budget.** The in-bucket weekly snapshot to `snapshots/YYYY-MM-DD/` (`docs/RUNBOOK.md` §3.2) is in place and protects against accidental deletion. A Helsinki cross-region backup is *prepared in code* (`photos:backup-to-prefix --target=hel1`, `s3_backup` filesystem disk, scheduler `when()`-gated on `AWS_BACKUP_BUCKET`) but the target bucket has not been provisioned. Activation is a conscious cost-vs-value decision: at current data volumes (~2 GB) it would add a small monthly bucket + egress fee for a single-owner wedding site. Revisit when the platform hosts third-party events (not just the maintainer's own wedding). When activated, this register's location line and Privacy §5 must be updated in the same commit.
- **Bucket-level versioning / object-lock** on the primary bucket. Long-term follow-up. Blocked on Hetzner Object Storage feature availability.
