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
        'slug'     => PhotoAlbum::APP_GALLERY,
        'name'     => 'App-Galerie',
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
        'url'      => 'https://r2.example/a.jpg',
        'r2_key'   => 'photos/a.jpg',
    ]);
    Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url'      => 'https://r2.example/b.jpg',
        'r2_key'   => 'photos/b.jpg',
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
    $file  = UploadedFile::fake()->image('x.jpg');

    actingAsGuest($guest)->postJson('/api/photos', ['photo' => $file])
        ->assertStatus(403)
        ->assertJson(['code' => 'app_blocked']);
});
