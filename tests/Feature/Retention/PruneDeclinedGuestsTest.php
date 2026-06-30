<?php

namespace Tests\Feature\Retention;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneDeclinedGuestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_old_declined_guests_without_app_access_are_removed(): void
    {
        $user = User::factory()->create();

        $oldEvent = $this->makeEvent($user, now()->subDays(200));
        $freshEvent = $this->makeEvent($user, now()->subDays(30));

        $oldDeclined = $this->makeGuest($oldEvent, status: 'declined', appAccess: false);
        $oldButHasAccess = $this->makeGuest($oldEvent, status: 'declined', appAccess: true);
        $oldAccepted = $this->makeGuest($oldEvent, status: 'accepted', appAccess: false);
        $recentDeclined = $this->makeGuest($freshEvent, status: 'declined', appAccess: false);

        $this->artisan('app:prune-declined-guests')->assertExitCode(0);

        $this->assertDatabaseMissing('guests', ['id' => $oldDeclined->id]);
        $this->assertDatabaseHas('guests', ['id' => $oldButHasAccess->id]);
        $this->assertDatabaseHas('guests', ['id' => $oldAccepted->id]);
        $this->assertDatabaseHas('guests', ['id' => $recentDeclined->id]);
    }

    public function test_dry_run_reports_but_does_not_delete(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, now()->subYear());
        $guest = $this->makeGuest($event, status: 'declined', appAccess: false);

        $this->artisan('app:prune-declined-guests', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('guests', ['id' => $guest->id]);
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

    private function makeGuest(Event $event, string $status, bool $appAccess): Guest
    {
        return Guest::create([
            'event_id' => $event->id,
            'firstname' => 'Guest',
            'lastname' => Str::random(6),
            'rsvp_status' => $status,
            'app_access' => $appAccess,
        ]);
    }
}
