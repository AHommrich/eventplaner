<?php

namespace Tests\Feature\Photo;

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

    public function test_orphan_cleanup_removes_only_unreferenced_objects(): void
    {
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

    private function makePhoto(?string $key): Photo
    {
        $user = User::factory()->create();
        $event = Event::create(['user_id' => $user->id, 'name' => 'Test', 'slug' => 'test-'.uniqid(), 'date' => now()->addDay()]);
        $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Gallery', 'sort_order' => 0]);

        if ($key !== null) {
            Storage::disk('s3')->put($key, 'jpeg-bytes');
        }

        return Photo::create([
            'event_id' => $event->id,
            'album_id' => $album->id,
            'uploaded_by' => 'Tester',
            'url' => 'https://example.test/'.($key ?? 'no-key'),
            'r2_key' => $key,
        ]);
    }
}
