<?php

namespace Tests\Feature\Photo;

use App\Console\Commands\CleanupOrphanPhotos;
use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config(['filesystems.disks.s3.url' => 'https://bucket.test', 'app.url' => 'https://eveplan.test']);
    }

    public function test_deleting_a_photo_removes_the_r2_blob(): void
    {
        $photo = $this->makePhoto('photos/single.jpg');

        $photo->delete();

        Storage::disk('s3')->assertMissing('photos/single.jpg');
    }

    public function test_deleting_a_photo_without_r2_key_does_not_blow_up(): void
    {
        $photo = $this->makePhoto(null);

        $photo->delete();

        // No assertion needed; the observer must short-circuit when r2_key is null.
        $this->assertTrue(true);
    }

    public function test_deleting_a_legacy_photo_removes_the_blob_derived_from_its_url(): void
    {
        $photo = $this->makePhoto('photos/legacy.jpg', legacy: true);

        $photo->delete();

        Storage::disk('s3')->assertMissing('photos/legacy.jpg');
    }

    public function test_orphan_cleanup_removes_only_unreferenced_objects(): void
    {
        $this->claimBucket();
        $live = $this->makePhoto('photos/live.jpg');
        Storage::disk('s3')->put('photos/orphan.jpg', 'orphan-content');

        $this->artisan('photos:cleanup-orphans')->assertExitCode(0);

        Storage::disk('s3')->assertExists($live->r2_key);
        Storage::disk('s3')->assertMissing('photos/orphan.jpg');
    }

    public function test_orphan_cleanup_dry_run_touches_nothing(): void
    {
        Storage::disk('s3')->put('photos/orphan.jpg', 'orphan-content');

        $this->artisan('photos:cleanup-orphans', ['--dry-run' => true])->assertExitCode(0);

        Storage::disk('s3')->assertExists('photos/orphan.jpg');
    }

    public function test_orphan_cleanup_keeps_legacy_photos_without_r2_key_and_covers(): void
    {
        $this->claimBucket();
        $legacy = $this->makePhoto('photos/legacy.jpg', legacy: true);
        Storage::disk('s3')->put('photos/cover-like.jpg', 'cover');
        $legacy->event->update(['cover_image_url' => 'https://bucket.test/photos/cover-like.jpg']);

        $this->artisan('photos:cleanup-orphans')->assertExitCode(0);

        Storage::disk('s3')->assertExists('photos/legacy.jpg');
        Storage::disk('s3')->assertExists('photos/cover-like.jpg');
    }

    public function test_orphan_cleanup_refuses_to_delete_in_an_unclaimed_bucket(): void
    {
        Storage::disk('s3')->put('photos/orphan.jpg', 'orphan-content');

        $this->artisan('photos:cleanup-orphans')->assertExitCode(1);

        Storage::disk('s3')->assertExists('photos/orphan.jpg');
    }

    public function test_orphan_cleanup_refuses_to_delete_in_a_bucket_owned_by_another_instance(): void
    {
        // Shared-bucket incident: staging's sweep must never delete production's photos.
        Storage::disk('s3')->put(CleanupOrphanPhotos::OWNER_MARKER, 'https://other-instance.test');
        Storage::disk('s3')->put('photos/foreign.jpg', 'foreign-content');

        $this->artisan('photos:cleanup-orphans')->assertExitCode(1);
        $this->artisan('photos:cleanup-orphans', ['--claim' => true])->assertExitCode(1);

        Storage::disk('s3')->assertExists('photos/foreign.jpg');
    }

    public function test_orphan_cleanup_aborts_above_the_max_delete_threshold(): void
    {
        $this->claimBucket();
        Storage::disk('s3')->put('photos/a.jpg', 'a');
        Storage::disk('s3')->put('photos/b.jpg', 'b');

        $this->artisan('photos:cleanup-orphans', ['--max-delete' => 1])->assertExitCode(1);

        Storage::disk('s3')->assertExists('photos/a.jpg');
        Storage::disk('s3')->assertExists('photos/b.jpg');
    }

    public function test_check_missing_reports_rows_without_blob_and_skips_foreign_hosts(): void
    {
        $this->makePhoto('photos/present.jpg');
        $this->makePhoto('photos/gone.jpg', withBlob: false);
        $foreign = $this->makePhoto(null);
        $foreign->update(['url' => 'https://picsum.photos/seed/demo/800/600']);

        $this->artisan('photos:check-missing')
            ->expectsOutputToContain('photos/gone.jpg')
            ->assertExitCode(1);

        $this->assertSame(3, Photo::count());
    }

    public function test_check_missing_delete_removes_only_rows_without_blob(): void
    {
        $present = $this->makePhoto('photos/present.jpg');
        $gone = $this->makePhoto('photos/gone.jpg', withBlob: false);

        $this->artisan('photos:check-missing', ['--delete' => true])->assertExitCode(0);

        $this->assertModelExists($present);
        $this->assertModelMissing($gone);
    }

    private function claimBucket(): void
    {
        $this->artisan('photos:cleanup-orphans', ['--claim' => true])->assertExitCode(0);
    }

    /**
     * $legacy simulates a pre-r2_key upload: the blob lives at the URL path but
     * r2_key is null. $withBlob=false simulates a row whose blob is gone.
     */
    private function makePhoto(?string $key, bool $legacy = false, bool $withBlob = true): Photo
    {
        $user = User::factory()->create();
        $event = Event::create(['user_id' => $user->id, 'name' => 'Test', 'slug' => 'test-'.uniqid(), 'date' => now()->addDay()]);
        $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Gallery', 'sort_order' => 0]);

        if ($key !== null && $withBlob) {
            Storage::disk('s3')->put($key, 'jpeg-bytes');
        }

        return Photo::create([
            'event_id' => $event->id,
            'album_id' => $album->id,
            'uploaded_by' => 'Tester',
            'url' => 'https://bucket.test/'.($key ?? 'no-key'),
            'r2_key' => $legacy ? null : $key,
        ]);
    }
}
