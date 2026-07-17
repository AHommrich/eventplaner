<?php

use App\Jobs\NotifyAssignedNote;
use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a push when an assign-tier user creates an assigned todo', function () {
    Queue::fake();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $eventAdmin = User::factory()->create(['email_verified_at' => now()]);
    $manager = User::factory()->create(['email_verified_at' => now()]);
    $event->users()->attach($eventAdmin, ['role' => 'event_admin']);
    $event->users()->attach($manager, ['role' => 'event_manager']);
    $this->actingAs($eventAdmin)->withSession(['active_event_id' => $event->id]);

    $this->post(route('notes.store'), [
        'type' => 'todo',
        'title' => 'Set up chairs',
        'assignee_user_id' => $manager->id,
    ])->assertRedirect();

    $note = Note::query()->sole();
    Queue::assertPushed(NotifyAssignedNote::class, fn ($job) => $job->noteId === $note->id
        && $job->assigneeUserId === $manager->id);
});

it('queues only when the assignee actually changes', function () {
    Queue::fake();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $event = Event::factory()->for($owner, 'owner')->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $event->users()->attach($first, ['role' => 'event_manager']);
    $event->users()->attach($second, ['role' => 'event_manager']);
    $note = Note::factory()->todo()->create([
        'event_id' => $event->id,
        'author_user_id' => $owner->id,
        'assignee_user_id' => $first->id,
    ]);
    $this->actingAs($owner)->withSession(['active_event_id' => $event->id]);

    $this->patch(route('notes.update', $note), ['title' => 'Edited'])->assertRedirect();
    Queue::assertNothingPushed();

    $this->patch(route('notes.update', $note), ['assignee_user_id' => $second->id])->assertRedirect();
    Queue::assertPushed(NotifyAssignedNote::class, fn ($job) => $job->noteId === $note->id
        && $job->assigneeUserId === $second->id);
});

it('drops a queued notification when the assignment is stale', function () {
    [$owner, $manager, $event, $note] = (function () {
        $owner = User::factory()->create();
        $manager = User::factory()->create(['is_approved' => true, 'email_verified_at' => now()]);
        $event = Event::factory()->for($owner, 'owner')->create();
        $event->users()->attach($manager, ['role' => 'event_manager']);
        $note = Note::factory()->todo()->create([
            'event_id' => $event->id,
            'author_user_id' => $owner->id,
            'assignee_user_id' => $manager->id,
        ]);

        return [$owner, $manager, $event, $note];
    })();
    $note->update(['assignee_user_id' => null]);
    $service = Mockery::mock(\App\Services\ExpoPushService::class);
    $service->shouldNotReceive('sendAssignedNote');

    (new NotifyAssignedNote($note->id, $manager->id))->handle($service);
});
