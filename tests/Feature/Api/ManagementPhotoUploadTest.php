<?php

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploadAlbum(Event $event, string $slug): PhotoAlbum
{
    return PhotoAlbum::create(['event_id' => $event->id, 'slug' => $slug, 'name' => $slug, 'sort_order' => 1]);
}

function ownerUploadClient(Event $event, User $user): \Tests\TestCase
{
    return test()->withToken(managementTokenFor($user, $event))->withHeaders(managementHeaders($event));
}

it('lets an owner upload into the app gallery', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = uploadAlbum($event, PhotoAlbum::APP_GALLERY);

    ownerUploadClient($event, $owner)
        ->postJson('/api/management/photos', [
            'photo' => UploadedFile::fake()->image('party.jpg', 100, 100),
            'album_id' => $album->id,
            'description' => 'Cake table',
        ])
        ->assertCreated()
        ->assertJsonFragment(['album_id' => $album->id, 'uploader_role' => 'owner']);

    $photo = Photo::first();
    expect($photo->event_id)->toBe($event->id);
    expect($photo->guest_id)->toBeNull();
    Storage::disk('s3')->assertExists($photo->r2_key);
});

it('lets a manager upload into the presentation album', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $album = uploadAlbum($event, PhotoAlbum::PRESENTATION);

    ownerUploadClient($event, $manager)
        ->postJson('/api/management/photos', [
            'photo' => UploadedFile::fake()->image('slide.jpg', 100, 100),
            'album_id' => $album->id,
        ])
        ->assertCreated()
        ->assertJsonFragment(['uploader_role' => 'event_manager']);
});

it('rejects a generic upload into the photo_game album', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = uploadAlbum($event, PhotoAlbum::PHOTO_GAME);

    ownerUploadClient($event, $owner)
        ->postJson('/api/management/photos', [
            'photo' => UploadedFile::fake()->image('game.jpg', 100, 100),
            'album_id' => $album->id,
        ])
        ->assertStatus(422);

    expect(Photo::count())->toBe(0);
});

it('rejects uploading into an album of a foreign event', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $foreign = Event::factory()->create();
    $foreignAlbum = uploadAlbum($foreign, PhotoAlbum::APP_GALLERY);

    ownerUploadClient($event, $owner)
        ->postJson('/api/management/photos', [
            'photo' => UploadedFile::fake()->image('x.jpg', 100, 100),
            'album_id' => $foreignAlbum->id,
        ])
        ->assertForbidden();

    expect(Photo::count())->toBe(0);
});

it('validates the uploaded file', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $album = uploadAlbum($event, PhotoAlbum::APP_GALLERY);

    ownerUploadClient($event, $owner)
        ->postJson('/api/management/photos', ['album_id' => $album->id])
        ->assertStatus(422);
});
