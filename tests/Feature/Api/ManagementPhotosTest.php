<?php

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function managementPhoto(Event $event, PhotoAlbum $album, string $key): Photo
{
    Storage::disk('s3')->put($key, 'photo-bytes');

    return Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'uploaded_by' => 'Organizer',
        'uploader_user_id' => $event->user_id,
        'uploader_role' => 'owner',
        'url' => Storage::disk('s3')->url($key),
        'r2_key' => $key,
    ]);
}

function managementAlbum(Event $event, string $slug, int $order): PhotoAlbum
{
    return PhotoAlbum::create([
        'event_id' => $event->id,
        'slug' => $slug,
        'name' => $slug,
        'sort_order' => $order,
    ]);
}

it('lets a manager list and delete photos across every gallery', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $event->users()->attach($manager, ['role' => 'event_manager']);

    $app = managementAlbum($event, PhotoAlbum::APP_GALLERY, 1);
    $presentation = managementAlbum($event, PhotoAlbum::PRESENTATION, 2);
    $game = managementAlbum($event, PhotoAlbum::PHOTO_GAME, 3);
    $appPhoto = managementPhoto($event, $app, 'photos/app.jpg');
    $presentationPhoto = managementPhoto($event, $presentation, 'photos/presentation.jpg');
    $gamePhoto = managementPhoto($event, $game, 'photos/game.jpg');

    $client = $this->withToken(managementTokenFor($manager))->withHeaders(managementHeaders($event));
    $client->getJson('/api/management/photos')
        ->assertOk()
        ->assertJsonCount(3, 'albums')
        ->assertJsonFragment(['id' => $appPhoto->id])
        ->assertJsonFragment(['id' => $presentationPhoto->id])
        ->assertJsonFragment(['id' => $gamePhoto->id]);

    $client->deleteJson("/api/management/photos/{$appPhoto->id}")->assertNoContent();
    Storage::disk('s3')->assertMissing('photos/app.jpg');

    $client->deleteJson('/api/management/photos', [
        'ids' => [$presentationPhoto->id, $gamePhoto->id],
    ])->assertNoContent();
    Storage::disk('s3')->assertMissing('photos/presentation.jpg');
    Storage::disk('s3')->assertMissing('photos/game.jpg');
    expect(Photo::count())->toBe(0);
});

it('rejects single and batch deletion when any photo belongs to another event', function () {
    $ownerA = User::factory()->create(['is_approved' => true]);
    $eventA = Event::factory()->for($ownerA, 'owner')->create();
    $albumA = managementAlbum($eventA, PhotoAlbum::APP_GALLERY, 1);
    $ownPhoto = managementPhoto($eventA, $albumA, 'photos/own.jpg');

    $eventB = Event::factory()->create();
    $albumB = managementAlbum($eventB, PhotoAlbum::APP_GALLERY, 1);
    $foreignPhoto = managementPhoto($eventB, $albumB, 'photos/foreign.jpg');
    $client = $this->withToken(managementTokenFor($ownerA))->withHeaders(managementHeaders($eventA));

    $client->deleteJson("/api/management/photos/{$foreignPhoto->id}")->assertForbidden();
    $client->deleteJson('/api/management/photos', [
        'ids' => [$ownPhoto->id, $foreignPhoto->id],
    ])->assertForbidden();

    expect(Photo::whereKey($ownPhoto->id)->exists())->toBeTrue()
        ->and(Photo::whereKey($foreignPhoto->id)->exists())->toBeTrue();
    Storage::disk('s3')->assertExists('photos/own.jpg');
    Storage::disk('s3')->assertExists('photos/foreign.jpg');
});

it('requires the management event header for photo reads and writes', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $token = managementTokenFor($owner);

    $this->withToken($token)->getJson('/api/management/photos')->assertForbidden();
    $this->withToken($token)->deleteJson('/api/management/photos', ['ids' => [1]])->assertForbidden();
});
