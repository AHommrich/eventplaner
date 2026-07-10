<?php

use App\Models\Event;
use App\Models\Guest;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeAppGalleryAlbum(Event $event): PhotoAlbum
{
    return PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => PhotoAlbum::APP_GALLERY,
        'name' => 'App-Galerie',
    ]);
}

it('lists app-gallery photos for the guest', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/a.jpg',
        'r2_key' => 'photos/a.jpg',
    ]);
    Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/b.jpg',
        'r2_key' => 'photos/b.jpg',
    ]);

    actingAsGuest($guest)->getJson('/api/photos')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('uploads a jpeg photo to the configured disk', function () {
    Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeAppGalleryAlbum($event);

    $file = UploadedFile::fake()->image('selfie.jpg', 400, 400);

    $response = actingAsGuest($guest)->postJson('/api/photos', ['photo' => $file])
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'url', 'guest_name', 'created_at']);

    expect(Photo::where('event_id', $event->id)->count())->toBe(1);

    $photo = Photo::first();
    Storage::disk('s3')->assertExists($photo->r2_key);
});

it('rejects upload of an invalid file format', function () {
    Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeAppGalleryAlbum($event);

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    actingAsGuest($guest)->postJson('/api/photos', ['photo' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo']);
});

it('rejects upload without a bearer token', function () {
    Storage::fake('s3');
    $file = UploadedFile::fake()->image('x.jpg');

    $this->postJson('/api/photos', ['photo' => $file])->assertStatus(401);
});

it('rejects upload when app_access is disabled', function () {
    Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = Guest::factory()->withoutAppAccess()->create(['event_id' => $event->id]);
    $file = UploadedFile::fake()->image('x.jpg');

    actingAsGuest($guest)->postJson('/api/photos', ['photo' => $file])
        ->assertStatus(403)
        ->assertJson(['code' => 'app_blocked']);
});

it('lets a guest delete their own app-gallery photo', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);
    Storage::disk('s3')->put('photos/own.jpg', 'image-bytes');

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/own.jpg',
        'r2_key' => 'photos/own.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertNoContent();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('s3')->assertMissing('photos/own.jpg');
});

it('removes a deleted guest photo from the gallery list', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/own.jpg',
        'r2_key' => 'photos/own.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertNoContent();

    actingAsGuest($guest)->getJson('/api/photos')
        ->assertOk()
        ->assertJsonMissing(['id' => $photo->id]);
});

it('rejects deleting another guests photo from the same event', function () {
    $event = Event::factory()->create();
    $viewer = Guest::factory()->create(['event_id' => $event->id]);
    $uploader = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $uploader->id,
        'url' => 'https://r2.example/other.jpg',
        'r2_key' => 'photos/other.jpg',
    ]);

    actingAsGuest($viewer)->deleteJson("/api/photos/{$photo->id}")
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
});

it('returns not found when deleting a photo from another event', function () {
    $ownEvent = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $ownEvent->id]);
    $otherGuest = Guest::factory()->create(['event_id' => $otherEvent->id]);
    $album = makeAppGalleryAlbum($otherEvent);

    $photo = Photo::create([
        'event_id' => $otherEvent->id,
        'album_id' => $album->id,
        'guest_id' => $otherGuest->id,
        'url' => 'https://r2.example/other-event.jpg',
        'r2_key' => 'photos/other-event.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertNotFound();

    expect(Photo::find($photo->id))->not->toBeNull();
});

it('rejects deleting organizer photos', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => null,
        'uploaded_by' => 'Organizer',
        'url' => 'https://r2.example/organizer.jpg',
        'r2_key' => 'photos/organizer.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertForbidden();

    expect(Photo::find($photo->id))->not->toBeNull();
});

it('does not let guests delete photo-game photos through the gallery endpoint', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => PhotoAlbum::PHOTO_GAME,
        'name' => 'Foto-Spiel',
    ]);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/game.jpg',
        'r2_key' => 'photos/game.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertNotFound();

    expect(Photo::find($photo->id))->not->toBeNull();
});

it('rejects photo deletion without a bearer token', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/own.jpg',
        'r2_key' => 'photos/own.jpg',
    ]);

    $this->deleteJson("/api/photos/{$photo->id}")
        ->assertUnauthorized();
});

it('rejects photo deletion when app_access is disabled', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->withoutAppAccess()->create(['event_id' => $event->id]);
    $album = makeAppGalleryAlbum($event);

    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2.example/own.jpg',
        'r2_key' => 'photos/own.jpg',
    ]);

    actingAsGuest($guest)->deleteJson("/api/photos/{$photo->id}")
        ->assertStatus(403)
        ->assertJson(['code' => 'app_blocked']);
});
