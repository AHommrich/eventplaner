<?php

use App\Models\Event;
use App\Models\Note;
use App\Models\User;

it('lists only notes visible to the authenticated manager', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $other = User::factory()->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);

    $personal = Note::factory()->create(['event_id' => $event->id, 'author_user_id' => $manager->id]);
    $assigned = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);
    $private = Note::factory()->create(['event_id' => $event->id, 'author_user_id' => $other->id]);

    $this->withToken(managementTokenFor($manager))
        ->withHeaders(managementHeaders($event))
        ->getJson('/api/management/notes')
        ->assertOk()
        ->assertJsonFragment(['id' => $personal->id])
        ->assertJsonFragment(['id' => $assigned->id])
        ->assertJsonMissing(['id' => $private->id])
        ->assertJsonPath('can_assign', false)
        ->assertJsonCount(0, 'assigned_team');
});

it('lets an owner assign and manage a todo for an active event manager', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $client = $this->withToken(managementTokenFor($owner))->withHeaders(managementHeaders($event));

    $client->postJson('/api/management/notes', [
        'type' => 'todo',
        'title' => 'Set up chairs',
        'assignee_user_id' => $manager->id,
    ])->assertCreated()->assertJsonPath('note.assignee_user_id', $manager->id);

    $note = Note::firstOrFail();
    $client->patchJson("/api/management/notes/{$note->id}", ['title' => 'Set up all chairs'])
        ->assertOk()
        ->assertJsonPath('note.title', 'Set up all chairs');

    $client->deleteJson("/api/management/notes/{$note->id}")->assertNoContent();
    expect($note->fresh()->trashed())->toBeTrue();
});

it('coerces every assigned entry to a completable todo', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $client = $this->withToken(managementTokenFor($owner))->withHeaders(managementHeaders($event));

    $created = $client->postJson('/api/management/notes', [
        'type' => 'note',
        'title' => 'Assigned through a stale client',
        'assignee_user_id' => $manager->id,
    ])->assertCreated()->assertJsonPath('note.type', 'todo');

    $note = Note::findOrFail($created->json('note.id'));
    $client->patchJson("/api/management/notes/{$note->id}", ['type' => 'note'])
        ->assertOk()
        ->assertJsonPath('note.type', 'todo');

    expect($note->fresh()->type)->toBe('todo');
});

it('lets an assignee toggle done but forbids text edits and assignment creation', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['is_approved' => true]);
    $otherManager = User::factory()->create();
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $event->users()->attach($otherManager, ['role' => 'event_manager']);
    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);
    $client = $this->withToken(managementTokenFor($manager))->withHeaders(managementHeaders($event));

    $client->patchJson("/api/management/notes/{$note->id}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('note.is_done', true);
    $client->patchJson("/api/management/notes/{$note->id}", ['title' => 'Hacked'])->assertForbidden();
    $client->postJson('/api/management/notes', [
        'type' => 'todo',
        'title' => 'Forbidden assignment',
        'assignee_user_id' => $otherManager->id,
    ])->assertForbidden();
});

it('cross-event guards management note mutations', function () {
    $ownerA = User::factory()->create();
    $eventA = Event::factory()->for($ownerA, 'owner')->create();
    $note = Note::factory()->create(['event_id' => $eventA->id, 'author_user_id' => $ownerA->id]);
    $ownerB = User::factory()->create(['is_approved' => true]);
    $eventB = Event::factory()->for($ownerB, 'owner')->create();
    $client = $this->withToken(managementTokenFor($ownerB))->withHeaders(managementHeaders($eventB));

    $client->patchJson("/api/management/notes/{$note->id}", ['title' => 'Cross event'])->assertForbidden();
    $client->deleteJson("/api/management/notes/{$note->id}")->assertForbidden();
});

it('requires X-Event-ID for management notes', function () {
    $owner = User::factory()->create(['is_approved' => true]);

    $this->withToken(managementTokenFor($owner))
        ->getJson('/api/management/notes')
        ->assertForbidden();
});
