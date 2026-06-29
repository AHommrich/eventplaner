<?php

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use Illuminate\Support\Facades\Storage;

it('shows the photos page', function () {
    actingAsOwner();

    $this->get(route('photos'))->assertOk();
});

it('deletes a single photo and removes it from R2', function () {
    Storage::fake('s3');
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Galerie']);

    Storage::disk('s3')->put('photos/x.jpg', 'fake');
    $photo = Photo::create([
        'event_id' => $event->id, 'album_id' => $album->id,
        'url' => 'https://r2/photos/x.jpg', 'r2_key' => 'photos/x.jpg',
    ]);

    $this->delete(route('photos.destroy', $photo))->assertRedirect();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('s3')->assertMissing('photos/x.jpg');
});

it('batch-deletes multiple photos', function () {
    Storage::fake('s3');
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Galerie']);

    $p1 = Photo::create(['event_id' => $event->id, 'album_id' => $album->id, 'url' => '/a.jpg', 'r2_key' => 'photos/a.jpg']);
    $p2 = Photo::create(['event_id' => $event->id, 'album_id' => $album->id, 'url' => '/b.jpg', 'r2_key' => 'photos/b.jpg']);

    $this->delete(route('photos.destroy-batch'), ['ids' => [$p1->id, $p2->id]])->assertRedirect();

    expect(Photo::whereIn('id', [$p1->id, $p2->id])->count())->toBe(0);
});
