<?php

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use Illuminate\Support\Facades\Storage;

it('shows the photos page', function () {
    actingAsOwner();

    $this->get(route('photos'))->assertOk();
});

it('deletes a single photo and removes it from object storage', function () {
    Storage::fake('s3');
    $user = actingAsOwner();
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
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Galerie']);

    $p1 = Photo::create(['event_id' => $event->id, 'album_id' => $album->id, 'url' => '/a.jpg', 'r2_key' => 'photos/a.jpg']);
    $p2 = Photo::create(['event_id' => $event->id, 'album_id' => $album->id, 'url' => '/b.jpg', 'r2_key' => 'photos/b.jpg']);

    $this->delete(route('photos.destroy-batch'), ['ids' => [$p1->id, $p2->id]])->assertRedirect();

    expect(Photo::whereIn('id', [$p1->id, $p2->id])->count())->toBe(0);
});

it('rejects destroy for a photo from another event', function () {
    Storage::fake('s3');
    actingAsOwner();
    $foreignEvent = Event::factory()->create();
    $foreignAlbum = PhotoAlbum::create(['event_id' => $foreignEvent->id, 'slug' => 'app_gallery', 'name' => 'Fremd']);
    Storage::disk('s3')->put('photos/foreign.jpg', 'do-not-delete');
    $foreignPhoto = Photo::create([
        'event_id' => $foreignEvent->id,
        'album_id' => $foreignAlbum->id,
        'url' => '/foreign.jpg',
        'r2_key' => 'photos/foreign.jpg',
    ]);

    $this->delete(route('photos.destroy', $foreignPhoto))->assertStatus(403);

    expect(Photo::find($foreignPhoto->id))->not->toBeNull();
    Storage::disk('s3')->assertExists('photos/foreign.jpg');
});

it('rejects assigning a projector album from another event', function () {
    actingAsOwner();
    $foreignEvent = Event::factory()->create();
    $foreignAlbum = PhotoAlbum::create(['event_id' => $foreignEvent->id, 'slug' => 'app_gallery', 'name' => 'Fremd']);

    $this->patch(route('photos.projector-album'), ['album_id' => $foreignAlbum->id])
        ->assertSessionHasErrors('album_id');
});
