# R2 → Hetzner Object Storage Migration

_Executed 2026-07-01._

## Why

The original photo storage was Cloudflare R2 with `AWS_DEFAULT_REGION=auto` — objects likely placed in Europe by proximity but not guaranteed. To make the "processing stays in the EU" claim on `/datenschutz` truthful without ambiguity, storage was consolidated onto Hetzner, where the app + database already run. Side benefits: one sub-processor fewer, one billing relationship, existing Hetzner AVV covers Object Storage automatically.

Trade-off accepted: Hetzner Object Storage has no built-in CDN. For a wedding app with mostly-German guests hitting a bucket in Nürnberg (same location as the app server), the latency difference is not observable.

## Result

- Hetzner Object Storage bucket `eveplan-photos-prod` in Nürnberg (`nbg1.your-objectstorage.com`), public-read.
- 36 objects (all `covers/*` and `photos/*` prefixes) copied 1:1 from R2. `rclone check` reports 0 differences.
- All production URLs in the `photos.url` and `events.cover_image_url` columns rewritten from the R2 `pub-<hash>.r2.dev` host to the Hetzner bucket URL.
- Coolify staging + production env vars (`AWS_ENDPOINT`, `AWS_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_URL`, `AWS_DEFAULT_REGION`) point at the new bucket.
- Cloudflare R2 bucket kept live for a 24–48h stabilisation window, then deleted. Cloudflare account itself cancelled unless still used for DNS/proxy.

The `photos.r2_key` column name is historical — the value is now a Hetzner object key. Rename left for a future non-urgent DB migration to avoid touching the observer + related code paths.

## Migration playbook (recorded for future storage moves)

### 1. Bucket

Cloudflare Cloud Console → Storage → Object Storage → Create bucket.

- Location: same as the app server (Nürnberg / nbg1 in our case). Latency and cost both benefit.
- Name: globally unique, immutable. We used `eveplan-photos-prod`.
- Object Lock: **off**. WORM would conflict with GDPR Art. 17 (right to erasure).
- Save the generated access key ID + secret key — shown only once.

### 2. Copy objects

Local rclone, using ephemeral env-var config (no permanent rclone.conf with secrets on disk):

```bash
export RCLONE_CONFIG_R2_TYPE=s3
export RCLONE_CONFIG_R2_PROVIDER=Cloudflare
export RCLONE_CONFIG_R2_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID          # R2 creds
export RCLONE_CONFIG_R2_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY
export RCLONE_CONFIG_R2_ENDPOINT=$AWS_ENDPOINT

export RCLONE_CONFIG_HETZNER_TYPE=s3
export RCLONE_CONFIG_HETZNER_PROVIDER=Other
export RCLONE_CONFIG_HETZNER_ACCESS_KEY_ID=$HETZNER_S3_ACCESS_KEY_ID
export RCLONE_CONFIG_HETZNER_SECRET_ACCESS_KEY=$HETZNER_S3_SECRET_ACCESS_KEY
export RCLONE_CONFIG_HETZNER_ENDPOINT=$HETZNER_S3_ENDPOINT

# Dry-run first
rclone copy "r2:$AWS_BUCKET" "hetzner:$HETZNER_S3_BUCKET" --dry-run -v

# Real copy
rclone copy "r2:$AWS_BUCKET" "hetzner:$HETZNER_S3_BUCKET" --progress --transfers 8

# Deep verify (sizes + hashes)
rclone check "r2:$AWS_BUCKET" "hetzner:$HETZNER_S3_BUCKET"
# → "0 differences found" means the copy is bit-identical
```

### 3. Env-var cutover in Coolify

Per environment (staging first, prod second):

- `AWS_ENDPOINT=https://nbg1.your-objectstorage.com` (region-level, not per-bucket)
- `AWS_BUCKET=eveplan-photos-prod`
- `AWS_ACCESS_KEY_ID=<Hetzner key>`
- `AWS_SECRET_ACCESS_KEY=<Hetzner secret>`
- `AWS_URL=https://eveplan-photos-prod.nbg1.your-objectstorage.com` (public read URL prefix)
- `AWS_DEFAULT_REGION=nbg1`

Redeploy each environment. Verify with a fresh photo upload — the resulting URL should point at Hetzner.

### 4. Rewrite existing URLs in the DB

Old rows still have R2 `pub-*.r2.dev` URLs. Run once per environment in the app container:

```bash
php artisan tinker --execute="
\DB::update(\"UPDATE photos SET url = REPLACE(url, 'https://pub-a2a31e651f4448e7983507b7c20e576a.r2.dev', 'https://eveplan-photos-prod.nbg1.your-objectstorage.com') WHERE url LIKE 'https://pub-a2a31e651f4448e7983507b7c20e576a.r2.dev/%'\");
\DB::update(\"UPDATE events SET cover_image_url = REPLACE(cover_image_url, 'https://pub-a2a31e651f4448e7983507b7c20e576a.r2.dev', 'https://eveplan-photos-prod.nbg1.your-objectstorage.com') WHERE cover_image_url LIKE 'https://pub-a2a31e651f4448e7983507b7c20e576a.r2.dev/%'\");
"
```

24 photos + 2 covers were rewritten in production. The remaining ~10 R2 objects (36 copied − 26 referenced) were storage orphans from historical upload failures — they now exist as orphans on Hetzner too, ~5 MB total. Ignored.

### 5. Cancel R2

Kept R2 running for 24–48h after the DB URL rewrite as a safety net for hidden references (email templates, caches). Then in Cloudflare dashboard: R2 → bucket → delete. Cloudflare account cancelled if not used for anything else.

## Rollback plan

Until step 5 (R2 deletion), rollback is: revert the six Coolify env vars to R2 values, redeploy, undo the tinker URL rewrite by running the same REPLACE with `from`/`to` swapped. Between step 5 and step 2 being redone, rollback would require restoring R2 objects from the backup you should have taken before starting (see Related files below).

## Related files

- [`sub-processors.md`](sub-processors.md) — Cloudflare moved to "Inactive / removed" section; Hetzner entry expanded to include Object Storage.
- `resources/js/pages/Legal/Privacy.vue` — section 5 rewritten, Cloudflare paragraph removed, Hetzner paragraph now covers both hosting + storage.
- `config/filesystems.php` — the `s3` disk unchanged (S3 driver works for both R2 and Hetzner OS); only the env vars behind it differ.
- `app/Observers/PhotoObserver.php`, `app/Console/Commands/CleanupOrphanPhotos.php` — docblocks updated to point at Hetzner instead of R2; `r2_key` column name kept for historical reasons.
