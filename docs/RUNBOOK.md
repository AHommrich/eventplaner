# Runbook — eveplan.de / beta.hommrich.app

Operational reference for the Eventplaner stack. Written for the single maintainer (André). If someone else has to keep this alive tomorrow, this is where they start.

Last touched: 2026-07-05.

> Sections marked **TODO** are stubs to be filled by the maintainer once the corresponding setup step in Coolify has been performed. Update this file *in the same commit* as the Coolify change — otherwise the runbook drifts.

---

## 1. Stack overview

- **VPS:** Hetzner CX22 / 4 GB RAM, Falkenstein. Host IP `142.132.165.232`.
- **Orchestration:** Coolify, self-hosted on the VPS.
- **Domains:**
  - `eveplan.de` → `production` branch → Dockerfile.prod (nginx + php-fpm) + separate MariaDB container
  - `beta.hommrich.app` → `staging` branch → same layout
- **Object storage:** Hetzner Object Storage, bucket `eveplan-photos-prod` in Nürnberg.
- **Mail:** Resend (EU region Ireland), domain `eveplan.de`.
- **Error monitoring:** Sentry EU region (Frankfurt) — since 2026-07-05.
- **Source control / CI:** GitHub Actions on push to `develop` / `staging` / `production`.

The MariaDB service is **not** a Coolify-managed database — it is a plain docker-compose service inside the same application stack. That has one consequence you need to know about right away: **Coolify's built-in database backup feature does not apply here**. Backups are handled via a dedicated `mysqldump` cron, see section 3.

---

## 2. Coolify configuration checklist

The application resource in Coolify needs the following settings. If you spin up a fresh resource or migrate to a new Coolify instance, walk this list top to bottom.

### 2.1 Health check
- Coolify → application resource → **Healthchecks** tab
- Path: `/up`
- Method: `GET`
- Expected status: `200`
- Interval: 30 seconds (default is fine)
- Failure threshold: 3
- Container is restarted after 3 consecutive failures. Laravel already exposes `/up` via `bootstrap/app.php` (`health: '/up'`), and Sentry ignores that path (`ignore_transactions` in `config/sentry.php`) so it won't flood ingest.

### 2.2 Zero-downtime deployment
- Coolify → application resource → **Advanced** → **Zero downtime deployment**: ON
- Effect: Coolify starts the new container, waits for the health check, then swaps traffic. If the new container never becomes healthy, the old container keeps serving.

### 2.3 Notifications
Currently not configured. **Do this before you start relying on the app in production.**

Recommended channel: Discord (personal server) or Telegram. Discord is easier to skim.

- Coolify → **Notifications** → add a channel (Discord webhook URL, Telegram bot + chat id, or plain SMTP).
- Enable at least these event types:
  - Deployment failed
  - Deployment succeeded (optional — noisy but useful in the beginning)
  - Container status changed (healthy ↔ unhealthy)
  - Server disk usage above threshold
- Test the channel once via the Coolify test-message button; do not go live blind.

### 2.4 Environment variables
Coolify → application resource → **Environment Variables**. Compare against `.env.example`; anything missing needs to be added here (Coolify does not read a checked-in `.env` file). The list below is not exhaustive — it lists the ones that are easy to forget.

- `APP_KEY` — long-lived, rotating it invalidates all sessions. Never share across staging/production.
- `APP_URL` — `https://eveplan.de` / `https://beta.hommrich.app`.
- `APP_ENV` — `production` / `staging`.
- `APP_DEBUG` — `false`. Always. Never turn on in a live environment.
- Database: `DB_HOST=db`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` — the docker-compose file has default fallbacks (`root` / `secret`) which are **only for local dev**. Override every single one in Coolify.
- Object storage (Hetzner): `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION=nbg1`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT=false`.
- Mail (Resend): `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `MAIL_MAILER=resend`.
- OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
- **Sentry (new, 2026-07-05):**
  - `SENTRY_LARAVEL_DSN=<the DSN from de.sentry.io>` — set on staging and production only.
  - `SENTRY_TRACES_SAMPLE_RATE=0.1` — keeps performance ingest inside the 10k free-tier budget.
  - `SENTRY_LOG_LEVEL=warning` — only warnings and above ship to Sentry (keeps error-event ingest well under 5k/month).
  - `LOG_STACK=daily,sentry_logs` — adds the Sentry log channel next to the existing daily file log.
- Retention overrides (optional): `RETENTION_INVITATION_TOKENS_DAYS`, `RETENTION_DECLINED_GUESTS_DAYS`.

### 2.5 Log retention
- Coolify → application resource → **Storage / Logs**: nothing custom needed. The nginx access log inside the app container resets on every deploy (documented in `docs/legal/sub-processors.md`). Laravel's `daily` channel keeps 14 files (`LOG_DAILY_DAYS=14`).

### 2.6 Scheduled tasks (Laravel scheduler)
Laravel's scheduler is triggered by `routes/console.php`. For it to actually run, Coolify needs to invoke `php artisan schedule:run` once per minute. Two options:

- **Preferred:** Coolify → application resource → **Scheduled Tasks** → add a task
  - Command: `php artisan schedule:run`
  - Frequency: every minute (`* * * * *`)
  - Container: the application container
- Alternative: system cron on the VPS host executing `docker exec laravel-app php artisan schedule:run` — works but is easier to lose track of.

Verify with `docker exec laravel-app php artisan schedule:list` — should list the retention + cleanup jobs from `routes/console.php`.

### 2.7 Resource limits (mind the 4 GB RAM incident)
- App container: no hard memory limit set today. If the container starts leaking, the OOM killer will hit the whole VPS. Adding a memory limit (e.g. `deploy.resources.limits.memory: 1.5g` in the compose file) contains the blast radius. **TODO** — decide + measure.
- Never trigger a redeploy on `staging` and `production` at the same time. See `CLAUDE.md` and `README.md` for the 2026-07-01 incident that established this rule.

---

## 3. Backup & Restore

The stack is protected in three layers. Understand what each one covers *and what it does not* — none of them is a full replacement for the others.

### 3.1 Layer 1 — Hetzner Cloud Backups (VPS snapshots)
- **Status:** enabled on the Hetzner Cloud panel.
- **What is inside:** the whole VPS filesystem, including the `db_data` docker volume that holds MariaDB — so guest data, RSVPs, drink logs, invitation tokens and photo *metadata* are backed up here.
- **What is NOT inside:** the Hetzner Object Storage bucket (`eveplan-photos-prod`). Object Storage is a separate Hetzner product with its own lifecycle. Cloud Backup does not touch it. Photo binaries need Layer 2.
- **Retention:** 7 rolling daily snapshots managed by Hetzner. Fine-grained restore is not possible — you either roll the whole VPS back to a snapshot or spin up a new server from a snapshot.
- **Consistency note:** the snapshot is taken cold on the VM filesystem. For small MariaDB instances (our situation, MB-scale, low concurrency) InnoDB crash recovery handles this gracefully on restart. For big write-heavy databases you would want a logical `mysqldump` on top; here it would be redundant.
- **Restore procedure:** Hetzner Cloud console → server → Backups tab → select snapshot → "Restore into this server". Downtime ~5–10 min. If you only need to recover a single row, do NOT restore the whole VPS — dump the DB out of the current live volume, extract the row manually.

### 3.2 Layer 2 — In-bucket weekly snapshot (accidental-delete protection)
- **Status:** implemented in `app/Console/Commands/BackupPhotoBucket.php` (`photos:backup-to-prefix`), scheduled weekly on Sundays at 04:00 UTC via `routes/console.php`.
- **What it does:** server-side copies (S3 `CopyObject`, no download/re-upload) every object under `photos/` and `covers/` into a dated `snapshots/YYYY-MM-DD/…` prefix in the same bucket. Idempotent — reruns on the same day are safe. Retention: last 4 dated snapshots (roughly one month of coverage), older ones auto-deleted.
- **Threat model it covers:**
  - Bug in application code that deletes individual objects (`PhotoObserver`, `photos:cleanup-orphans` with a wrong `--prefix`, mis-firing DELETE cascade).
  - "I deleted the wrong photo" incidents where the user notices within four weeks.
- **Threat model it does NOT cover:**
  - Bucket entirely deleted from the Hetzner panel — the snapshots go with it.
  - API credentials compromised — attacker has equal access to `photos/`, `covers/` **and** `snapshots/`.
  - Regional Hetzner Object Storage outage in Nürnberg.
- The bigger threats above are only defensible with a bucket in another region (Hetzner Helsinki, `hel1`) or with bucket-level versioning / object-lock. Both are tracked as follow-ups in `docs/AUDIT_ACTION_PLAN.md`.
- **Manual run (before a risky migration, for example):**
  ```bash
  docker exec laravel-app php artisan photos:backup-to-prefix
  # inspect first
  docker exec laravel-app php artisan photos:backup-to-prefix --dry-run
  ```
- **Restore a single lost object:**
  ```bash
  # 1. Locate it in the latest snapshot
  docker exec laravel-app php artisan tinker
  # > Storage::disk('s3')->allFiles('snapshots/2026-07-05/photos');
  # 2. Copy the object back in place
  # > Storage::disk('s3')->copy('snapshots/2026-07-05/photos/<uuid>.jpg', 'photos/<uuid>.jpg');
  # 3. If the DB `photos` row was deleted too, restore it out of the Hetzner Cloud Backup (Layer 1)
  ```
- **Restore a whole prefix (photos or covers):**
  ```bash
  # tinker one-liner — replace <date> with the most recent snapshot directory
  docker exec laravel-app php artisan tinker --execute='foreach (Storage::disk("s3")->allFiles("snapshots/<date>/photos") as $k) { Storage::disk("s3")->copy($k, str_replace("snapshots/<date>/", "", $k)); }'
  ```

### 3.3 Layer 3 — Manual DB dump (optional; only before risky deploys)
Hetzner Cloud Backup already covers the DB every 24 h. A manual dump is only needed when:
- You expect a migration to be irreversible and want a finer-grained rollback point than the 24 h snapshot cycle.
- You want a *logical* backup (portable SQL text, provider-independent).

```bash
ssh root@<VPS-IP>
docker exec mariadb sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \
  --single-transaction --routines --triggers --all-databases' \
  > /root/eveplan-$(date +%F-%H%M).sql
gzip /root/eveplan-*.sql
# never leave the only copy on the VPS
scp root@<VPS-IP>:/root/eveplan-*.sql.gz ~/backups/eveplan/
```

Restore from that dump:
```bash
gunzip -c ~/backups/eveplan/<file>.sql.gz \
  | docker exec -i mariadb sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD"'
```

### 3.4 Migration rollback
Laravel migrations do **not** have a robust auto-rollback for schema changes that have already touched data. Treat each migration as one-way unless it has a working `down()` **and** you have a fresh backup.

- **Before**: manual dump (§3.3) or verify Hetzner snapshot age is <24h.
- **After failed migration**: `php artisan migrate:status` — see what ran. If a mid-migration partial state needs cleanup, do it via the `mysql` client. Do not click "redeploy" in Coolify until the DB is consistent again, otherwise the same migration will fire on the same broken state.
- **Full rollback via Hetzner snapshot**: last resort, 5–10 min downtime.

---

## 4. Common incidents

### 4.1 Deploy fails in Coolify
- Check the deploy log in Coolify UI for the exact failure step.
- If the failure is inside `npm run build` or `composer install`, retry once — transient network issues are common on the VPS.
- If the failure is inside `php artisan migrate --force`, **do not** click redeploy blindly. Migrations that fail partway can leave the schema in a half-applied state. Inspect what ran and what didn't:
  ```bash
  docker exec laravel-app php artisan migrate:status
  ```
  If a broken migration needs to be reverted manually, do it on the VPS via `mysql` client — Laravel has no reliable auto-rollback here.

### 4.2 Container becomes unhealthy
- Coolify auto-restarts after 3 failed health checks. If the loop stays broken, the notification channel (once configured, see 2.3) fires.
- SSH in, `docker logs laravel-app --tail=200`, then `docker exec laravel-app tail -n 200 storage/logs/laravel-*.log`.
- If Sentry is live it should have received a stack trace — check `de.sentry.io` before spending time on logs.

### 4.3 Database container down
- `docker ps` on the VPS shows `mariadb` status.
- Volume is `db_data` — as long as it is not removed, the data survives a container restart.
- Recreate: `docker compose up -d db` (Coolify does this automatically on a redeploy).

### 4.4 VPS unresponsive
- Hetzner Cloud Console: log in, take a screenshot of the graphs before rebooting. RAM curve tells you whether it was OOM.
- Emergency reset: Hetzner console → server → power → hard reboot. Same procedure that was used in the 2026-07-01 OOM incident.
- After the VPS is back: `systemctl status coolify` (or the equivalent) — Coolify itself may have been the victim.

### 4.5 Domain / TLS problem
- Coolify uses Traefik as the ingress. Traefik logs live in the Coolify UI under the proxy resource.
- Certificate renewal is automatic via Let's Encrypt. If it fails, the Traefik log tells you why (rate limiting, DNS TXT missing, port 80 unreachable).

---

## 5. Change log

- **2026-07-05** — Runbook created. Sentry EU integration landed the same day (`docs/legal/sub-processors.md`, `docs/AUDIT_ACTION_PLAN.md` Muss-Punkt 6).
