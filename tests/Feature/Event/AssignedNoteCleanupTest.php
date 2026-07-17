<?php

use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use App\Services\EventAccessService;

/*
 * Checkpoint 4.2 — assigned notes must not stay booked on a user who leaves the
 * event_manager tier (removed or promoted). EventAccessService reverts them to
 * plain unassigned notes (assignee_user_id = null) inside its transaction.
 */

function eventWithAssignedTodo(): array
{
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create();
    $event->users()->attach($manager->id, ['role' => 'event_manager']);

    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);

    return [$event, $manager, $note];
}

it('unassigns a managers notes when they are removed from the event', function () {
    [$event, $manager, $note] = eventWithAssignedTodo();

    app(EventAccessService::class)->removeMember($event, $manager);

    expect($note->fresh()->assignee_user_id)->toBeNull();
});

it('unassigns a managers notes when they are promoted out of the manager tier', function () {
    [$event, $manager, $note] = eventWithAssignedTodo();

    app(EventAccessService::class)->setMemberRole($event, $manager, 'event_admin');

    expect($note->fresh()->assignee_user_id)->toBeNull();
});

it('keeps assignments when the member stays an event_manager', function () {
    [$event, $manager, $note] = eventWithAssignedTodo();

    // A no-op re-sync to the same tier must not detach the assignment.
    app(EventAccessService::class)->setMemberRole($event, $manager, 'event_manager');

    expect($note->fresh()->assignee_user_id)->toBe($manager->id);
});

it('does not touch another events assignments', function () {
    [$event, $manager, $note] = eventWithAssignedTodo();

    $otherEvent = Event::factory()->create();
    $otherNote = Note::factory()->todo()->create([
        'event_id' => $otherEvent->id,
        'author_user_id' => $otherEvent->user_id,
        'assignee_user_id' => $manager->id,
    ]);

    app(EventAccessService::class)->removeMember($event, $manager);

    expect($otherNote->fresh()->assignee_user_id)->toBe($manager->id);
});
