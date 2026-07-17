<?php

namespace App\Jobs;

use App\Models\Note;
use App\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAssignedNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $noteId,
        public int $assigneeUserId,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ExpoPushService $push): void
    {
        $note = Note::with(['assignee', 'event'])->find($this->noteId);

        // A delayed job must not notify a stale assignee after reassignment,
        // note deletion, or removal of the manager from this event.
        if (! $note
            || $note->assignee_user_id !== $this->assigneeUserId
            || ! $note->assignee
            || ! $note->assignee->hasVerifiedEmail()
            || ! $note->assignee->isApproved()) {
            return;
        }

        $stillActiveManager = $note->event->users()
            ->where('users.id', $this->assigneeUserId)
            ->wherePivot('role', 'event_manager')
            ->exists();

        if (! $stillActiveManager) {
            return;
        }

        $push->sendAssignedNote($note->assignee, $note);
    }
}
