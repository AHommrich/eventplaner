<?php

use App\Models\EventPhotoGame;
use App\Models\EventTaskOverride;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Models\PhotoGameAssignment;
use App\Models\PhotoGameTask;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Support\Facades\Storage;

it('shows the photo game admin page', function () {
    actingAsOwner();

    $this->get(route('photo-game.index'))->assertOk();
});

it('starts a new game (creates as active)', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('photo-game.start'))->assertRedirect();

    $game = EventPhotoGame::where('event_id', $event->id)->first();
    expect($game)->not->toBeNull();
    expect($game->status)->toBe(EventPhotoGame::STATUS_ACTIVE);
});

it('ends an active game', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $game = EventPhotoGame::create(['event_id' => $event->id, 'status' => 'active']);

    $this->post(route('photo-game.end'))->assertRedirect();

    expect($game->fresh()->status)->toBe(EventPhotoGame::STATUS_ENDED);
});

it('updates the catalog (type) for the game', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = PhotoGameTaskCatalog::create([
        'event_id' => null, 'name' => 'Hochzeit', 'event_type' => 'hochzeit', 'is_base' => false, 'is_active' => true,
    ]);

    $this->patch(route('photo-game.catalog'), ['catalog_id' => $catalog->id])->assertRedirect();

    $game = EventPhotoGame::where('event_id', $event->id)->first();
    expect($game->catalog_id)->toBe($catalog->id);
});

it('upserts a hidden override for an existing task', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = PhotoGameTaskCatalog::create(['event_id' => null, 'name' => 'Base', 'is_base' => true, 'is_active' => true]);
    $task = PhotoGameTask::create(['catalog_id' => $catalog->id, 'description' => 'X', 'is_active' => true]);

    $this->post(route('photo-game.overrides.upsert'), [
        'task_id' => $task->id,
        'action' => 'hidden',
    ])->assertRedirect();

    expect(EventTaskOverride::where('event_id', $event->id)->where('task_id', $task->id)->where('action', 'hidden')->exists())->toBeTrue();
});

it('upserts an added override (custom task)', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('photo-game.overrides.upsert'), [
        'action' => 'added',
        'custom_text' => 'Foto vom Brautpaar',
    ])->assertRedirect();

    expect(EventTaskOverride::where('event_id', $event->id)
        ->where('action', 'added')
        ->where('custom_text', 'Foto vom Brautpaar')
        ->exists())->toBeTrue();
});

it('deletes a task override', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $override = EventTaskOverride::create(['event_id' => $event->id, 'task_id' => null, 'action' => 'added', 'custom_text' => 'Test']);

    $this->delete(route('photo-game.overrides.destroy', $override))->assertRedirect();

    expect(EventTaskOverride::find($override->id))->toBeNull();
});

it('deletes an assignment and removes its photo from R2', function () {
    Storage::fake('s3');
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $album = PhotoAlbum::create(['event_id' => $event->id, 'slug' => 'photo_game', 'name' => 'Fotospiel']);
    $game = EventPhotoGame::create(['event_id' => $event->id, 'status' => 'active']);
    $catalog = PhotoGameTaskCatalog::create(['event_id' => null, 'name' => 'Base', 'is_base' => true, 'is_active' => true]);
    $task = PhotoGameTask::create(['catalog_id' => $catalog->id, 'description' => 'X', 'is_active' => true]);
    $guest = \App\Models\Guest::factory()->create(['event_id' => $event->id]);

    Storage::disk('s3')->put('photos/task.jpg', 'fake');
    $photo = Photo::create([
        'event_id' => $event->id,
        'album_id' => $album->id,
        'guest_id' => $guest->id,
        'url' => 'https://r2/photos/task.jpg',
        'r2_key' => 'photos/task.jpg',
    ]);
    $assignment = PhotoGameAssignment::create([
        'game_id' => $game->id, 'guest_id' => $guest->id, 'task_id' => $task->id, 'photo_id' => $photo->id,
    ]);

    $this->delete(route('photo-game.assignments.destroy', $assignment))->assertRedirect();

    expect(PhotoGameAssignment::find($assignment->id))->toBeNull();
    Storage::disk('s3')->assertMissing('photos/task.jpg');
});
