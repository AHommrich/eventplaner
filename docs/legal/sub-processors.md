# Sub-Processor Register

_Last reviewed: 2026-06-30_

This register lists every third party that processes personal data on behalf of eveplan. It is the authoritative source the user-facing privacy policy (`/datenschutz`) quotes from. **Any new sub-processor must be added here before the integration ships to production** — otherwise the published privacy policy becomes a lie.

GDPR Art. 28 requires a written data-processing agreement with each processor. Art. 30 requires us to maintain this very list. Signed PDF copies live outside the repository (never commit them) — file paths below point at the local filing location.

---

## Active processors

### Hetzner Online GmbH
- **Purpose:** Application server + managed MariaDB hosting
- **Data categories:** Everything — user accounts, events, guests, sessions, log files
- **Provider address:** Industriestr. 25, 91710 Gunzenhausen, Germany
- **Customer number:** K0847634225
- **Location of processing:** Germany (Falkenstein) — confirmed EU-only by §3 of the AVV
- **Data Processing Agreement:** Hetzner AVV v1.2, **signed 2026-06-30**
  - Local filing: `~/Downloads/dpa-2026-06-30.pdf` (move to `~/legal/eventplaner/dpa-hetzner-2026-06-30.pdf`)
- **Authorised sub-sub-processors per Annex 3 of the AVV:** Hetzner Finland Oy (EU). The US (Hetzner US LLC, NTT, QTS) and Singapore (Hetzner SG, NTT SG1) entries do not apply because our server is in an EU location — Annex 3 footnote: *„Soweit Sie sich für einen Serverstandort in der EU entschieden haben, werden Ihre Serverdaten ausschließlich innerhalb der EU verarbeitet."*
- **Provider privacy notice:** https://www.hetzner.com/de/legal/privacy-policy

### Cloudflare R2 (Cloudflare, Inc.)
- **Purpose:** Object storage for uploaded photos (configured as the `s3` disk in `config/filesystems.php`, pointing at the R2 endpoint `bfc3bc81c7d1b7de90f621cea2bf15f5.r2.cloudflarestorage.com`)
- **Data categories:** Photo binary + object key. **EXIF metadata is stripped server-side** on every upload via `App\Services\PhotoSanitizer`: Intervention/Image re-encodes the file to a JPEG, dropping EXIF, IPTC and XMP segments before the bytes ever leave the app server. Verified by `tests/Feature/Photo/ExifStrippingTest.php`.
- **Account:** Andrehommrich@googlemail.com's Account
- **Location of processing:** R2 region currently `auto` (config/`.env`: `AWS_DEFAULT_REGION=auto`). For full EU residency consider pinning a specific EU jurisdiction at bucket level — Cloudflare R2 supports jurisdictional buckets ("EU").
- **Data Processing Agreement:** Cloudflare DPA v6.4 (effective 2026-04-03)
  - Cloudflare incorporates the DPA automatically into the Self-Serve Subscription Agreement — see dashboard → Manage Account → Configurations → "Data processing addendum". No separate signature step exists for Self-Serve customers.
  - Local filing: `~/Downloads/CLOUDFLARE DATA PROCESSING ADDENDUM _ Cloudflare.pdf` (snapshot saved 2026-06-30) — move to `~/legal/eventplaner/dpa-cloudflare-2026-06-30.pdf`
- **Provider privacy notice / source of DPA:** https://www.cloudflare.com/cloudflare-customer-dpa/

### Resend (Plus Five Five, Inc., d/b/a Resend)
- **Purpose:** Transactional email — verification, password reset, invitations
- **Data categories:** Recipient email address, name (as embedded in mail body), template content
- **Location of processing:** United States. Transfers from EEA/UK to the US covered by **EU SCCs + UK SCCs** as defined in Section 6.2 of the DPA.
- **Data Processing Agreement:** Resend DPA, last updated 2025-12-31
  - Resend states that the DPA "becomes legally binding upon Customer entering into the Agreement" — no separate signature step required for Self-Serve customers.
  - Local filing: `~/Downloads/Data Processing Addendum · Resend.pdf` (snapshot saved 2026-06-30) — move to `~/legal/eventplaner/dpa-resend-2026-06-30.pdf`
- **Provider privacy notice / source of DPA:** https://resend.com/legal/dpa

### Google LLC (only for "Sign in with Google" users)
- **Purpose:** OAuth login. Triggered only when the user actively clicks the Google button on the login/register screen.
- **Data categories:** Google account id, email address, display name (default OpenID Connect claims only — no calendar, contacts, photos)
- **Location of processing:** United States / global
- **Data Processing Agreement:** Google's standard processor terms for OAuth integrations apply automatically; no separate signed copy required for a SaaS using public OAuth scopes.
- **Provider privacy notice:** https://policies.google.com/privacy

### GitHub, Inc.
- **Purpose:** Source-code hosting and CI execution. **No production user data**, but commit metadata is technically personal data of the contributor (commit author name + email).
- **Data categories:** Source code, commit author identity
- **Location of processing:** United States
- **Data Processing Agreement:** GitHub DPA (link only, no per-customer signature for free public repos)
- **Provider privacy notice:** https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement

---

## Inactive / removed processors

_None yet._

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
- **Nginx logs (inside the app container).** `access_log` on production still uses the compiled-in default location `/var/log/nginx/access.log`. The container is recreated on every Coolify deploy (weekly or more often), which resets the file — effective retention is bounded by the deploy cadence rather than a rotation window. **Follow-up**: switch to `access_log off;` in `Dockerfile.prod`, since we do not use access logs for analytics and the upstream Coolify proxy already keeps its own request log.
- **Hetzner-side system logs.** Hetzner has hypervisor-level visibility (VM boot, network abuse reports) but no direct access to the guest filesystem. Their retention is governed by §4 + §7 of the Hetzner AVV (limited-purpose processing, deletion after contract termination). No customer-side action available.
- **Coolify proxy logs.** Coolify runs its own reverse proxy in front of the app container. Its access-log retention is controlled by the Coolify installation config, not by this application. Documented here so we remember it exists.

## Known gaps (tracked for follow-up)

- **R2 jurisdiction.** The bucket currently uses `AWS_DEFAULT_REGION=auto`. For strict EU residency, recreate the bucket in a Cloudflare R2 EU jurisdiction (see [`r2-eu-jurisdiction-migration.md`](r2-eu-jurisdiction-migration.md)) and switch the env. Until then we rely on Cloudflare's standard DPA SCCs for any potential transfer outside the EU.
- **Nginx access log off-switch.** Documented under "Log retention" above — flip `access_log off;` in `Dockerfile.prod` on the next deploy touch to make the reset-on-deploy behaviour a proper policy rather than an accidental one.
