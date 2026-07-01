<?php

use App\Models\Event;
use App\Models\PhotoAlbum;
use App\Models\User;

it('creates the three standard albums when an admin creates a new event', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $this->actingAs($admin);

    $this->post(route('events.store'), ['name' => 'Test Event']);

    $event = Event::where('name', 'Test Event')->first();
    $slugs = PhotoAlbum::where('event_id', $event->id)->pluck('slug')->sort()->values()->toArray();

    expect($slugs)->toEqual([PhotoAlbum::APP_GALLERY, PhotoAlbum::PHOTO_GAME, PhotoAlbum::PRESENTATION]);
});

it('creates the three standard albums when an event-request is approved', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $applicant = User::factory()->create();
    $request = \App\Models\EventRequest::create([
        'user_id' => $applicant->id, 'event_name' => 'Genehmigtes Event', 'status' => 'pending',
    ]);

    $this->actingAs($admin)->post(route('requests.event-requests.approve', $request));

    $event = Event::where('user_id', $applicant->id)->where('name', 'Genehmigtes Event')->first();
    expect(PhotoAlbum::where('event_id', $event->id)->count())->toBe(3);
});

it('uploads via /api/photos land in the app_gallery album', function () {
    \Illuminate\Support\Facades\Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = \App\Models\Guest::factory()->create(['event_id' => $event->id]);
    $gallery = PhotoAlbum::create(['event_id' => $event->id, 'slug' => PhotoAlbum::APP_GALLERY, 'name' => 'Galerie']);

    actingAsGuest($guest)->postJson('/api/photos', [
        'photo' => \Illuminate\Http\UploadedFile::fake()->image('x.jpg'),
    ])->assertStatus(201);

    $photo = \App\Models\Photo::first();
    expect($photo->album_id)->toBe($gallery->id);
});

it('uploads via /api/game/photo/submit land in the photo_game album', function () {
    \Illuminate\Support\Facades\Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = \App\Models\Guest::factory()->create(['event_id' => $event->id]);
    $game = \App\Models\EventPhotoGame::create(['event_id' => $event->id, 'status' => 'active']);
    $catalog = \App\Models\PhotoGameTaskCatalog::create(['event_id' => null, 'name' => 'Base', 'is_base' => true, 'is_active' => true]);
    \App\Models\PhotoGameTask::create(['catalog_id' => $catalog->id, 'description' => 'X', 'is_active' => true]);
    $gameAlbum = PhotoAlbum::create(['event_id' => $event->id, 'slug' => PhotoAlbum::PHOTO_GAME, 'name' => 'Spiel']);

    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(201);
    actingAsGuest($guest)->postJson('/api/game/photo/submit', [
        'photo' => \Illuminate\Http\UploadedFile::fake()->image('x.jpg'),
    ])->assertOk();

    $photo = \App\Models\Photo::first();
    expect($photo->album_id)->toBe($gameAlbum->id);
});
