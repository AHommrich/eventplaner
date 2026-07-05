<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

it('copies photos and covers into a dated snapshot prefix', function () {
    Carbon::setTestNow('2026-07-05 04:00:00');

    Storage::disk('s3')->put('photos/a.jpg', 'A');
    Storage::disk('s3')->put('photos/b.jpg', 'B');
    Storage::disk('s3')->put('covers/e1.jpg', 'C');

    $this->artisan('photos:backup-to-prefix')->assertSuccessful();

    Storage::disk('s3')->assertExists('snapshots/2026-07-05/photos/a.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-07-05/photos/b.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-07-05/covers/e1.jpg');

    // originals untouched
    Storage::disk('s3')->assertExists('photos/a.jpg');
    Storage::disk('s3')->assertExists('covers/e1.jpg');
});

it('is idempotent on re-run for the same day', function () {
    Carbon::setTestNow('2026-07-05');
    Storage::disk('s3')->put('photos/a.jpg', 'A');

    $this->artisan('photos:backup-to-prefix')->assertSuccessful();
    // second run should not fail, should not double-write
    $this->artisan('photos:backup-to-prefix')->assertSuccessful();

    Storage::disk('s3')->assertExists('snapshots/2026-07-05/photos/a.jpg');
});

it('prunes older snapshots beyond the retention window', function () {
    // seed 5 old snapshots
    foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'] as $d) {
        Storage::disk('s3')->put("snapshots/{$d}/photos/x.jpg", 'stale');
    }
    Carbon::setTestNow('2026-07-05');
    Storage::disk('s3')->put('photos/current.jpg', 'C');

    $this->artisan('photos:backup-to-prefix', ['--keep' => 4])->assertSuccessful();

    // 5 old + 1 new = 6 dirs, keep 4 → oldest two must be gone
    Storage::disk('s3')->assertMissing('snapshots/2026-06-01/photos/x.jpg');
    Storage::disk('s3')->assertMissing('snapshots/2026-06-08/photos/x.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-06-15/photos/x.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-06-29/photos/x.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-07-05/photos/current.jpg');
});

it('dry-run neither copies nor deletes', function () {
    Carbon::setTestNow('2026-07-05');
    Storage::disk('s3')->put('photos/a.jpg', 'A');
    Storage::disk('s3')->put('snapshots/2026-01-01/photos/old.jpg', 'stale');

    $this->artisan('photos:backup-to-prefix', ['--keep' => 1, '--dry-run' => true])
        ->assertSuccessful();

    Storage::disk('s3')->assertMissing('snapshots/2026-07-05/photos/a.jpg');
    Storage::disk('s3')->assertExists('snapshots/2026-01-01/photos/old.jpg');
});
