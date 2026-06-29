<?php

use App\Models\Event;
use App\Models\EventPhotoGame;
use App\Models\Guest;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameTask;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeBaseCatalogWithTask(string $desc = 'Eine Aufgabe'): PhotoGameTask
{
    $catalog = PhotoGameTaskCatalog::create([
        'event_id'  => null,
        'name'      => 'Base',
        'is_base'   => true,
        'is_active' => true,
    ]);
    return PhotoGameTask::create([
        'catalog_id'  => $catalog->id,
        'description' => $desc,
        'is_active'   => true,
    ]);
}

function makeGameSetup(Event $event, string $status = EventPhotoGame::STATUS_ACTIVE): EventPhotoGame
{
    return EventPhotoGame::create([
        'event_id' => $event->id,
        'status'   => $status,
    ]);
}

it('returns draft status when no game exists yet', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/game/photo/status')
        ->assertOk()
        ->assertJson(['status' => 'draft', 'assignment' => null]);
});

it('returns status with active game and no assignment yet', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event);

    actingAsGuest($guest)->getJson('/api/game/photo/status')
        ->assertOk()
        ->assertJson(['status' => 'active', 'assignment' => null]);
});

it('assigns a new task from the pool', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event);
    makeBaseCatalogWithTask('Foto von der Torte');

    $response = actingAsGuest($guest)->postJson('/api/game/photo/assign')
        ->assertStatus(201);

    expect($response->json('task.description'))->toBe('Foto von der Torte');
});

it('rejects assign when game is not active', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event, EventPhotoGame::STATUS_DRAFT);
    makeBaseCatalogWithTask();

    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(422);
});

it('returns 409 when guest already has an assignment', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event);
    makeBaseCatalogWithTask();

    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(201);
    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(409);
});

it('submits a photo for an open assignment', function () {
    Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event);
    makeBaseCatalogWithTask();
    PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'photo_game', 'name' => 'Fotospiel']);

    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(201);

    $response = actingAsGuest($guest)
        ->postJson('/api/game/photo/submit', ['photo' => UploadedFile::fake()->image('task.jpg')])
        ->assertOk()
        ->assertJsonStructure(['photo_url', 'submitted_at']);

    expect($response->json('photo_url'))->toContain('photos/');
});

it('allows re-submission for the same assignment', function () {
    Storage::fake('s3');
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeGameSetup($event);
    makeBaseCatalogWithTask();
    PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'photo_game', 'name' => 'Fotospiel']);

    actingAsGuest($guest)->postJson('/api/game/photo/assign')->assertStatus(201);
    actingAsGuest($guest)
        ->postJson('/api/game/photo/submit', ['photo' => UploadedFile::fake()->image('first.jpg')])
        ->assertOk();
    actingAsGuest($guest)
        ->postJson('/api/game/photo/submit', ['photo' => UploadedFile::fake()->image('second.jpg')])
        ->assertOk();
});
