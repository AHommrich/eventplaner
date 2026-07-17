<?php

use App\Models\Event;
use App\Models\Note;
use App\Models\User;

/*
 * P2 — Notes & ToDos authorization + integrity.
 */

function noteOwnerEvent(): array
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $event = Event::factory()->for($owner, 'owner')->create();

    return [$owner, $event];
}

function actingAsNoteMember(Event $event, string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $event->users()->attach($user->id, ['role' => $role]);
    test()->actingAs($user)->withSession(['active_event_id' => $event->id]);

    return $user;
}

it('lets a manager create a personal note', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');

    test()->post(route('notes.store'), ['type' => 'todo', 'title' => 'Buy flowers'])
        ->assertRedirect();

    $note = Note::first();
    expect($note->author_user_id)->toBe($manager->id);
    expect($note->assignee_user_id)->toBeNull();
    expect($note->event_id)->toBe($event->id);
});

it('forbids a manager from creating an assigned note', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');
    $other = actingAsNoteMember($event, 'event_manager');
    test()->actingAs($manager)->withSession(['active_event_id' => $event->id]);

    test()->post(route('notes.store'), [
        'type' => 'todo',
        'title' => 'Do X',
        'assignee_user_id' => $other->id,
    ])->assertForbidden();
});

it('lets an owner assign a todo to an active manager', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');
    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);

    test()->post(route('notes.store'), [
        'type' => 'todo',
        'title' => 'Set up chairs',
        'assignee_user_id' => $manager->id,
    ])->assertRedirect();

    expect(Note::first()->assignee_user_id)->toBe($manager->id);
});

it('rejects assigning a todo to a non-manager', function () {
    [$owner, $event] = noteOwnerEvent();
    $admin = actingAsNoteMember($event, 'event_admin');
    // event_admin acting, tries to assign to another event_admin (not a manager)
    $targetAdmin = actingAsNoteMember($event, 'event_admin');
    test()->actingAs($admin)->withSession(['active_event_id' => $event->id]);

    test()->post(route('notes.store'), [
        'type' => 'todo',
        'title' => 'Nope',
        'assignee_user_id' => $targetAdmin->id,
    ])->assertSessionHasErrors('assignee_user_id');
});

it('lets an assignee mark the todo done but not edit its text', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');
    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);

    test()->actingAs($manager)->withSession(['active_event_id' => $event->id]);

    // toggling done → ok
    test()->patch(route('notes.update', $note), ['is_done' => true])->assertRedirect();
    expect($note->fresh()->is_done)->toBeTrue();
    expect($note->fresh()->done_at)->not->toBeNull();

    // editing text → forbidden
    test()->patch(route('notes.update', $note), ['title' => 'Hacked'])->assertForbidden();
    expect($note->fresh()->title)->not->toBe('Hacked');
});

it('lets an owner edit and delete an assigned note', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');
    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);
    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);

    test()->patch(route('notes.update', $note), ['title' => 'Updated'])->assertRedirect();
    expect($note->fresh()->title)->toBe('Updated');

    test()->delete(route('notes.destroy', $note))->assertRedirect();
    expect($note->fresh()->trashed())->toBeTrue();
});

it('keeps personal notes private to their author', function () {
    [$owner, $event] = noteOwnerEvent();
    $author = actingAsNoteMember($event, 'event_manager');
    $note = Note::factory()->create([
        'event_id' => $event->id,
        'author_user_id' => $author->id,
        'assignee_user_id' => null,
    ]);

    $other = actingAsNoteMember($event, 'event_manager');
    test()->actingAs($other)->withSession(['active_event_id' => $event->id]);

    test()->patch(route('notes.update', $note), ['title' => 'X'])->assertForbidden();
    test()->delete(route('notes.destroy', $note))->assertForbidden();
});

it('cross-event guards note mutations', function () {
    [$ownerA, $eventA] = noteOwnerEvent();
    $note = Note::factory()->create(['event_id' => $eventA->id, 'author_user_id' => $ownerA->id]);

    [$ownerB, $eventB] = noteOwnerEvent();
    test()->actingAs($ownerB)->withSession(['active_event_id' => $eventB->id]);

    test()->patch(route('notes.update', $note), ['title' => 'X'])->assertForbidden();
    test()->delete(route('notes.destroy', $note))->assertForbidden();
});

it('shows the assigned-team list only to assign-tier users', function () {
    [$owner, $event] = noteOwnerEvent();
    $manager = actingAsNoteMember($event, 'event_manager');
    Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $manager->id,
    ]);

    // manager: no team list, cannot assign
    test()->actingAs($manager)->withSession(['active_event_id' => $event->id]);
    test()->get(route('notes.index'))
        ->assertOk()
        ->assertInertia(fn ($a) => $a->where('can_assign', false)->where('assigned_team', []));

    // owner: sees the team list
    test()->actingAs($owner)->withSession(['active_event_id' => $event->id]);
    test()->get(route('notes.index'))
        ->assertOk()
        ->assertInertia(fn ($a) => $a->where('can_assign', true)->count('assigned_team', 1));
});
