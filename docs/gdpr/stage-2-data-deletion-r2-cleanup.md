# Stage 2 — R2 photo cleanup on user / event deletion

**Effort:** ~1 h
**Outcome:** When a user deletes their account or removes an event, every Cloudflare-R2 object linked to that data is deleted from the bucket — not just NULL-ed in the database. An `artisan photos:cleanup-orphans` command sweeps anything that already drifted.
**Why this matters:** GDPR Art. 17 ("right to erasure") requires that deleted personal data is **actually gone**, not just hidden. The current state — `r2_key` set to NULL while the blob lives on — would fail an audit and quietly accumulate stale data.

## Today

`Photo` rows reference `r2_key` (the object key in the R2 bucket) and `url` (the public CDN URL). When a user is deleted:

- `events` cascade-delete via the `users.id` FK
- `photos` cascade with the event (`event_id` FK, `ON DELETE CASCADE`) — DB row gone
- **but the R2 object behind `r2_key` is never deleted** — it stays in the bucket indefinitely

Spot check via `grep -rn "Storage::disk('r2')->delete" app/` — the only place a blob is actively removed is when a single photo is deleted via the API/admin UI (`PhotoController::destroy`). Cascade deletions skip that path entirely.

## Steps

### 1. Model observer

`app/Observers/PhotoObserver.php` — new. Listens to the `deleting` event on the `Photo` model and removes the R2 object:

```php
public function deleting(Photo $photo): void
{
    if ($photo->r2_key) {
        Storage::disk('r2')->delete($photo->r2_key);
    }
}
```

Register in `AppServiceProvider::boot()`:

```php
Photo::observe(PhotoObserver::class);
```

This fires for **every** deletion path — single-photo delete, batch delete, cascade from event delete, cascade from user delete. One observer, all paths covered.

**Watch out:** `Photo::truncate()` and raw SQL deletes do **not** trigger model events. If you ever clean up via `DB::table('photos')->delete()`, the observer is bypassed. Don't do that — go through the model.

### 2. Cascade chain sanity check

Verify the chain `users → events → photos → R2` actually triggers the observer:

1. Delete a user (via the existing settings/profile delete flow)
2. Confirm the events were cascade-deleted
3. Confirm the photos were cascade-deleted (DB rows gone)
4. Confirm the R2 keys were removed (manually check the bucket, or use a stubbed `Storage::fake('r2')` in a test)

If photos are removed via raw cascade SQL and the observer doesn't fire, swap to soft cascade in the migration (`onDelete('cascade')` does fire model events when the parent uses Eloquent `delete()`, but not when MariaDB enforces the FK directly). Safer pattern: delete events with `Event::find($id)->delete()` from the controller, let Eloquent walk the relations.

### 3. Orphan sweep command

`app/Console/Commands/CleanupOrphanPhotos.php` — new. Lists all R2 objects under `photos/` and removes any whose key is not referenced by a `photos.r2_key` row anymore:

```php
public function handle(): int
{
    $live = Photo::whereNotNull('r2_key')->pluck('r2_key')->all();
    $bucket = Storage::disk('r2')->allFiles('photos');

    $orphans = array_diff($bucket, $live);

    if ($this->option('dry-run')) {
        $this->info('Would delete ' . count($orphans) . ' orphan objects.');
        return self::SUCCESS;
    }

    foreach ($orphans as $key) {
        Storage::disk('r2')->delete($key);
    }

    $this->info('Deleted ' . count($orphans) . ' orphan objects from R2.');
    return self::SUCCESS;
}
```

Flag: `--dry-run` so the first production run is observable.

### 4. Schedule the sweep

`bootstrap/app.php` (Laravel 11+ scheduler) — once a week is fine, this is a backstop:

```php
$schedule->command('photos:cleanup-orphans')->weekly();
```

### 5. Photo `url` field

`url` is a public CDN URL. Once the blob is gone, the URL 404s — that's fine. Leave the column alone, the `deleting` observer does its job before the DB row vanishes anyway.

### 6. Tests

`tests/Feature/Photo/PhotoCleanupTest.php` — new:

- `Storage::fake('r2')` + create a `Photo` with a fake key
- assert the file exists, delete the photo, assert the file is gone
- repeat for cascade: create user → event → photo, delete user, assert the file is gone
- run the orphan-cleanup command on a faked bucket with one stray key, assert it's removed and live photos survive

## File list

| File | Action |
|---|---|
| `app/Observers/PhotoObserver.php` | new |
| `app/Providers/AppServiceProvider.php` | register observer |
| `app/Console/Commands/CleanupOrphanPhotos.php` | new |
| `bootstrap/app.php` | schedule weekly run |
| `tests/Feature/Photo/PhotoCleanupTest.php` | new |

## Acceptance criteria

- [ ] Deleting a single photo via the admin UI removes both the DB row and the R2 object
- [ ] Deleting a user via `Settings → Delete Account` removes their events → photos → R2 objects, end to end
- [ ] `php artisan photos:cleanup-orphans --dry-run` reports orphan count without touching anything
- [ ] `php artisan photos:cleanup-orphans` removes only orphans, leaves live photos intact
- [ ] Scheduler entry registered, visible in `php artisan schedule:list`
- [ ] All three test cases pass against `Storage::fake('r2')`

## Commit suggestion

```
feat(photos): cascade R2 cleanup on user/event delete + orphan sweep command
```
