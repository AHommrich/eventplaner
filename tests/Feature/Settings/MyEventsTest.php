<?php

use App\Models\Event;
use App\Models\User;

/*
 * P3 — "My Events" settings page + role in the shared accessible_events prop.
 */

it('renders the my-events settings page with role badges', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $ownEvent = Event::factory()->for($owner, 'owner')->create();

    // a second event where the user is only an event_manager
    $foreign = Event::factory()->create();
    $foreign->users()->attach($owner->id, ['role' => 'event_manager']);

    test()->actingAs($owner)->withSession(['active_event_id' => $ownEvent->id]);

    test()->get(route('settings.events'))
        ->assertOk()
        ->assertInertia(function ($assert) use ($ownEvent, $foreign) {
            $events = collect($assert->toArray()['props']['accessible_events']);
            expect($events)->toHaveCount(2);
            expect($events->firstWhere('id', $ownEvent->id)['my_role'])->toBe('owner');
            expect($events->firstWhere('id', $foreign->id)['my_role'])->toBe('event_manager');
        });
});
