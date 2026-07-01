<?php

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoAlbum;

it('shows the projector page for a valid token', function () {
    $event = Event::factory()->create(['projector_token' => 'tok-'.uniqid()]);

    $this->get("/projector/{$event->projector_token}")
        ->assertOk();
});

it('returns 404 for an invalid projector token', function () {
    $this->get('/projector/does-not-exist-token')->assertStatus(404);
});

it('returns photos as JSON for /projector/{token}/photos', function () {
    $event = Event::factory()->create(['projector_token' => 'tok-'.uniqid()]);
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'app_gallery', 'name' => 'Galerie']);
    $event->update(['projector_album_id' => $album->id]);

    Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'url' => 'https://r2/x.jpg',
        'r2_key' => 'photos/x.jpg',
        'uploaded_by' => 'Test',
    ]);

    $this->getJson("/projector/{$event->projector_token}/photos")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('regenerates the projector token', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $oldToken = $event->projector_token;

    $this->post(route('photos.projector-token.regenerate'))->assertRedirect();

    expect($event->fresh()->projector_token)->not->toBe($oldToken);
});

it('updates projector_name_mode', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->patch(route('photos.projector-name-mode'), ['name_mode' => 'full'])->assertRedirect();

    expect($event->fresh()->projector_name_mode)->toBe('full');
});

it('updates projector_album_id', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'presentation', 'name' => 'Präsentation']);

    $this->patch(route('photos.projector-album'), ['album_id' => $album->id])->assertRedirect();

    expect($event->fresh()->projector_album_id)->toBe($album->id);
});
