<?php

namespace Tests\Feature\Retention;

use App\Models\Event;
use App\Models\Group;
use App\Models\InvitationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneInvitationTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_tokens_are_deleted_recent_ones_survive(): void
    {
        $user = User::factory()->create();

        $oldEvent = $this->makeEvent($user, now()->subDays(60));
        $freshEvent = $this->makeEvent($user, now()->subDays(2));

        $oldToken = $this->makeToken($oldEvent);
        $freshToken = $this->makeToken($freshEvent);

        $this->artisan('app:prune-invitation-tokens')->assertExitCode(0);

        $this->assertDatabaseMissing('invitation_tokens', ['id' => $oldToken->id]);
        $this->assertDatabaseHas('invitation_tokens', ['id' => $freshToken->id]);
    }

    public function test_dry_run_reports_but_does_not_delete(): void
    {
        $user = User::factory()->create();
        $oldEvent = $this->makeEvent($user, now()->subYear());
        $token = $this->makeToken($oldEvent);

        $this->artisan('app:prune-invitation-tokens', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('invitation_tokens', ['id' => $token->id]);
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

    private function makeToken(Event $event): InvitationToken
    {
        $group = Group::create(['event_id' => $event->id, 'name' => 'Group '.Str::random(4)]);

        return InvitationToken::create([
            'group_id' => $group->id,
            'token' => Str::random(32),
        ]);
    }
}
