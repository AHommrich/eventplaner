# Secret Rotation Log

Authoritative log of all secret rotations for the eventplaner stack.
Complements `SECURITY.md` (which describes the disclosure process) and
`docs/RUNBOOK.md` (which describes day-to-day operations).

## Cadence

| Secret                                            | Rotation trigger              |
| ------------------------------------------------- | ----------------------------- |
| Google OAuth Client Secret — beta client          | Semi-annual (Q1 + Q3)         |
| Google OAuth Client Secret — eveplan client       | Semi-annual (Q1 + Q3)         |
| Resend API Key                                    | Semi-annual (Q1 + Q3)         |
| Hetzner Object Storage — primary access key       | Semi-annual (Q1 + Q3)         |
| Hetzner Object Storage — backup access key        | Semi-annual (Q1 + Q3) — once active |
| `APP_KEY`                                         | Only on suspected compromise  |
| Sentry DSN                                        | Only on suspected compromise  |

Google is set up with **two OAuth clients** in the same Google Cloud
project, one per public environment:

- **beta client** — redirect URI `https://beta.hommrich.app/auth/google/callback`. Secret lives in Coolify staging only.
- **eveplan client** — redirect URIs `https://eveplan.de/auth/google/callback` **and** `http://localhost:8080/auth/google/callback`. Secret lives in Coolify production **and** the maintainer's local `.env`.

Both clients rotate on the same cadence but are independent — rotating
one does not touch the other. When the eveplan client rotates, the
maintainer's local `.env` must be updated in the same session, otherwise
local dev login breaks with `invalid_client` on the token exchange.

Reasoning:

- **Semi-annual rotation** for anything that can send outbound mail, mint
  auth tokens, or read/write user photos. Fresh credentials shrink the
  blast radius of an unnoticed leak (paste in a log, forgotten `.env`
  copy, ex-employee laptop).
- **On-compromise-only** for `APP_KEY` because rotating it invalidates
  every active session and every symmetric-encrypted payload
  (`encrypted:` casts, `Crypt::encrypt` calls). Rolling it prophylactically
  logs out every guest and forces re-linking of QR codes — worse than the
  risk it mitigates.
- Sentry DSN is a write-only ingestion token with no read capability, so
  the impact of a leak is spam, not exfiltration.

## Rotation procedures

### Google OAuth Client Secret — beta client (staging)

1. Google Cloud Console → APIs & Services → Credentials → the **beta**
   OAuth 2.0 client (redirect URI `beta.hommrich.app`).
2. Click **Add secret**. Google keeps the previous secret valid during
   the grace window — do **not** revoke it yet.
3. **Coolify staging** → env var `GOOGLE_CLIENT_SECRET` → save → redeploy.
4. Verify Google login on `https://beta.hommrich.app` — brand-new account
   flow **and** existing account re-login.
5. Google Cloud Console → **Delete** the old beta secret.
6. Append a log entry below.

### Google OAuth Client Secret — eveplan client (production + local dev)

1. Google Cloud Console → APIs & Services → Credentials → the **eveplan**
   OAuth 2.0 client (redirect URIs `eveplan.de` **and** `localhost:8080`).
2. Click **Add secret**. Google keeps the previous secret valid during
   the grace window — do **not** revoke it yet.
3. **Coolify production** → env var `GOOGLE_CLIENT_SECRET` → save →
   redeploy. Sequential; the 4 GB VPS cannot handle parallel Coolify
   redeploys — see `RUNBOOK.md` §5.
4. Verify Google login on `https://eveplan.de`.
5. Update the maintainer's local `.env` → `GOOGLE_CLIENT_SECRET=<new>` →
   `docker restart laravel-app`.
6. Verify Google login on `http://localhost:8080/auth/google/callback`.
   Skipping this step is how the eveplan client rotates cleanly on
   production but leaves local dev on the old secret; then the moment
   step 7 revokes it, local dev breaks with 401 `invalid_client`.
7. Google Cloud Console → **Delete** the old eveplan secret.
8. Append a log entry below.

### Resend API Key

1. Resend dashboard → API Keys → **Create API Key** with scope
   `Sending access` on domain `eveplan.de`.
2. Coolify staging → env var `RESEND_API_KEY` → save → redeploy.
3. Trigger a registration on staging → verification mail must arrive.
4. Coolify production → same env var → save → redeploy.
5. Trigger a registration on production → verification mail must arrive.
6. Update local `.env`.
7. Resend dashboard → revoke the old key.
8. Append a log entry below.

### Hetzner Object Storage — access keys

1. Hetzner Console → Object Storage → the affected bucket → **Access
   keys** → create new key pair.
2. Coolify staging → env vars `AWS_ACCESS_KEY_ID` +
   `AWS_SECRET_ACCESS_KEY` (or backup-bucket variants) → save → redeploy.
3. Smoke-test: upload one photo on staging, then load it back — this
   exercises `putObject` **and** the signed-URL `getObject` path.
4. Coolify production → same env vars → save → redeploy.
5. Upload one photo on production, then load it back.
6. Update local `.env`.
7. Hetzner Console → delete the old key pair.
8. Append a log entry below.

## Log

Chronological, newest at the top. Each entry: date, secret, reason.

- 2026-07-07 — Google OAuth Client Secret — beta client (scheduled rotation)
- 2026-07-07 — Google OAuth Client Secret — eveplan client (scheduled rotation)
