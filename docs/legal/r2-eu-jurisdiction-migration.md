# R2 EU Jurisdiction Migration

_Last reviewed: 2026-07-01_

## Why this document exists

The R2 bucket currently used by eveplan for photo storage is configured with `AWS_DEFAULT_REGION=auto`, which lets Cloudflare place objects in the closest region worldwide. For strict EU data residency — the position we take in the user-facing privacy policy (`/datenschutz`) and in [`sub-processors.md`](sub-processors.md) — the bucket must be pinned to Cloudflare's **EU jurisdiction**. Objects created there are guaranteed to stay in EU data centres.

This is a **planning document**, not a runbook to execute unattended. The migration touches production photo data, so treat every step as manual and reviewed.

## Scope

- Recreate the R2 bucket in the EU jurisdiction.
- Copy existing objects from the current (jurisdiction-less) bucket into the new one.
- Switch application configuration to point at the new bucket.
- Update the sub-processor register once the move is complete.

Out of scope: any change to the photo model, upload paths, or `PhotoSanitizer`. The migration is infrastructure only.

## Preconditions

- Access to the Cloudflare dashboard for the account listed in [`sub-processors.md`](sub-processors.md).
- API token with **R2 Storage: Edit** for both the source and destination buckets (only used during copy).
- A staging deploy window when uploads can be paused briefly — the switch is not zero-downtime for writes without a dual-write step (see "Alternative: dual-write cutover" at the bottom).
- Local `rclone` ≥ 1.65 installed if using the manual copy path.

## Step 1 — Create the destination bucket

1. Cloudflare dashboard → R2 → **Create bucket**.
2. Choose the same account as the current bucket.
3. Name: `eventplaner-photos-eu` (do not reuse the old name — you'll need both live simultaneously during the copy).
4. **Location: Jurisdiction → EU**. This is the only step that cannot be undone later — jurisdiction is fixed at bucket creation.
5. Copy the resulting endpoint URL. It has the form `https://<account>.eu.r2.cloudflarestorage.com` — note the `.eu.` segment; that is the tell.
6. Create a scoped API token (dashboard → R2 → **Manage R2 API Tokens**) with **Object Read + Write** on the new bucket only. Save the access key id + secret in the password vault, not in `.env`.

## Step 2 — Copy objects

Two supported approaches. Use **Super Slurper** unless the object count is trivial (< 1000).

### 2a — Cloudflare R2 Super Slurper (recommended)

1. Cloudflare dashboard → R2 → **Data Migration** → new job.
2. Source: existing bucket (S3-compatible endpoint + old access key/secret).
3. Destination: `eventplaner-photos-eu`.
4. Start the job. Super Slurper preserves object keys and metadata; the destination is identical to the source after completion.
5. Verify: for a random sample of ~20 keys, `HEAD` on both buckets and compare `Content-Length` + `ETag`. Any mismatch → stop and open a Cloudflare ticket.

### 2b — `rclone` (fallback if Super Slurper is unavailable)

Configure two remotes locally (`~/.config/rclone/rclone.conf`), then run:

```bash
rclone copy r2-old:eventplaner-photos r2-eu:eventplaner-photos-eu \
    --checksum --transfers 8 --checkers 16 --progress
```

- `--checksum` compares by ETag/MD5 rather than mtime; safer for object storage.
- Run to completion, then run **the same command again** with `--dry-run` — the second pass should report zero files to copy.

Keep the local config file out of version control; the tokens are secrets.

## Step 3 — Sanity-check the copy

Regardless of copy method:

- `rclone ls r2-old:eventplaner-photos | wc -l` **==** `rclone ls r2-eu:eventplaner-photos-eu | wc -l`
- Pick five random photos, download from both buckets, `sha256sum` — all pairs must match.
- Confirm no writes were happening during the copy (see Step 4).

## Step 4 — Cut writes over

Two options, ordered by preference.

### 4a — Short maintenance window (simplest)

1. On staging + production, `docker compose stop laravel-app` (or an equivalent block in Coolify) so no new uploads can land.
2. Run one final incremental copy (Super Slurper "resume" or `rclone copy` again).
3. Update env vars in Coolify (both staging and production, one after the other):
   - `AWS_BUCKET=eventplaner-photos-eu`
   - `AWS_ENDPOINT=https://<account>.eu.r2.cloudflarestorage.com`
   - `AWS_DEFAULT_REGION=auto` (R2 ignores the value; keep it as-is for AWS SDK compatibility)
   - `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` → the new EU-scoped token
4. Redeploy. Watch the Coolify logs for `PhotoSanitizer` uploads succeeding on the new endpoint.
5. Smoke-test: upload one photo from the web admin and one from the mobile app. Verify the object lands in the new bucket via the dashboard.

Expected downtime: **< 5 minutes** if the two-step incremental copy finishes cleanly.

### 4b — Dual-write cutover (zero downtime, more code)

Adds temporary code to write every upload to both buckets while backfill runs, then flips reads. Only worth the complexity if a live event is imminent and the maintenance window in 4a is unacceptable. The plan sketch:

1. Introduce a second disk in `config/filesystems.php` (`s3_eu`) pointing at the new endpoint.
2. In `PhotoSanitizer` (or the calling controllers), fan out `Storage::disk('s3')->put(...)` and `Storage::disk('s3_eu')->put(...)`.
3. Backfill via Super Slurper / rclone with writes still going to both.
4. Flip reads (`AWS_ENDPOINT`, `AWS_BUCKET`) after backfill completes.
5. Remove the dual-write code + retire `s3` from `config/filesystems.php`.

If you go this route, guard the dual-write behind an env flag so the fallback path is a single config change.

## Step 5 — Update documentation

Once the app is healthy on the new bucket for at least 24 hours:

1. Edit [`sub-processors.md`](sub-processors.md) → Cloudflare R2 section:
   - Change **Location of processing** from `auto` to `EU jurisdiction (Cloudflare R2 EU)`.
   - Add a **Migration history** subitem noting date + previous configuration.
   - Remove the "R2 jurisdiction" bullet from the "Known gaps" section at the bottom.
2. Edit `resources/js/pages/Legal/Privacy.vue` → section 5, Cloudflare paragraph, adjust the residency wording (currently references SCCs as a fallback; can be simplified to "EU jurisdiction, no transfer to third countries required").
3. Bump `_Last reviewed_` on both files.

## Step 6 — Delete the old bucket

**Wait at least 30 days after Step 4 before running this.** Deletion is irreversible and forfeits any chance of falling back if a lingering photo path was missed.

1. Verify no code path references the old endpoint: `grep -R "r2.cloudflarestorage.com" .` (excluding `docs/`). Only the doc references should remain.
2. Verify no environment on Coolify still holds the old credentials.
3. Cloudflare dashboard → R2 → old bucket → **Delete bucket**. Empty it first if the UI requires it.
4. Revoke the API token used only for the old bucket.
5. Add a **Migration history** entry to `sub-processors.md` noting the deletion date.

## Rollback

The migration is a copy, not a move — until Step 6 the old bucket is untouched. If anything looks wrong after the cutover:

1. Revert the four env vars from Step 4a on both staging and production.
2. Redeploy.
3. Any photos uploaded to the EU bucket during the failed cutover need to be copied back to the old bucket (`rclone copy r2-eu:... r2-old:...`), otherwise those specific uploads become unreachable when the app flips back.

The 30-day wait before Step 6 exists so this rollback window is not artificial.

## Cost note

R2 has no egress fees, so copying between buckets in the same account is free of transfer cost. Class A operations (writes) do count — for a bucket with ~5000 objects the migration should cost well under $1.

## Related files

- [`sub-processors.md`](sub-processors.md) — the register updated in Step 5.
- [`../gdpr/stage-6-subprocessor-documentation.md`](../gdpr/stage-6-subprocessor-documentation.md) — the GDPR stage that established the register.
- `config/filesystems.php` — where the disk is defined.
- `app/Services/PhotoSanitizer.php` — the code path that writes to R2 on every upload.
