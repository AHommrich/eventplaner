<?php

namespace Tests\Feature\Retention;

use App\Models\Event;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_old_soft_deleted_and_past_event_notes_only(): void
    {
        $user = User::factory()->create();
        $futureEvent = $this->makeEvent($user, now()->addMonth());
        $pastEvent = $this->makeEvent($user, now()->subDays(200));

        // 1. Soft-deleted long ago → purged.
        $oldTrashed = $this->makeNote($futureEvent);
        $oldTrashed->delete();
        $oldTrashed->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

        // 2. Soft-deleted recently → kept (still trashed).
        $recentTrashed = $this->makeNote($futureEvent);
        $recentTrashed->delete();

        // 3. Live note on a long-past event → purged.
        $pastLive = $this->makeNote($pastEvent);

        // 4. Live note on a future event → kept.
        $futureLive = $this->makeNote($futureEvent);

        $this->artisan('app:prune-notes')->assertExitCode(0);

        $this->assertDatabaseMissing('notes', ['id' => $oldTrashed->id]);
        $this->assertDatabaseMissing('notes', ['id' => $pastLive->id]);
        $this->assertDatabaseHas('notes', ['id' => $recentTrashed->id]);
        $this->assertDatabaseHas('notes', ['id' => $futureLive->id]);
    }

    public function test_dry_run_reports_but_does_not_delete(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, now()->subYear());
        $note = $this->makeNote($event);

        $this->artisan('app:prune-notes', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }

    private function makeEvent(User $user, \DateTimeInterface $date): Event
    {
        return Event::create([
            'user_id' => $user->id,
            'name' => 'Event '.Str::random(4),
            'slug' => 'event-'.Str::random(8),
            'date' => $date,
        ]);
    }

    private function makeNote(Event $event): Note
    {
        return Note::factory()->create(['event_id' => $event->id, 'author_user_id' => $event->user_id]);
    }
}
