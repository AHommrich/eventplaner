<?php

use App\Models\Event;
use App\Models\ScheduleItem;
use App\Models\User;

function scheduleItem(Event $event, string $title, int $order): ScheduleItem
{
    return ScheduleItem::create([
        'event_id' => $event->id,
        'title' => $title,
        'starts_at' => '14:30',
        'sort_order' => $order,
        'location_name' => 'Main Hall',
        'location_street' => 'Marktplatz',
        'location_house_number' => '1',
        'location_postal_code' => '90402',
        'location_city' => 'Nürnberg',
    ]);
}

it('returns the full schedule for the bound event', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    scheduleItem($event, 'Ceremony', 1);
    scheduleItem($event, 'Dinner', 2);

    test()->withToken(managementTokenFor($owner, $event))
        ->withHeaders(managementHeaders($event))
        ->getJson('/api/management/schedule')
        ->assertOk()
        ->assertJsonCount(2, 'schedule_stations')
        ->assertJsonFragment(['title' => 'Ceremony', 'starts_at' => '14:30'])
        ->assertJsonFragment(['location_name' => 'Main Hall', 'address' => 'Marktplatz 1, 90402 Nürnberg']);
});

it('does not expose a schedule mutation route', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();

    test()->withToken(managementTokenFor($owner, $event))
        ->withHeaders(managementHeaders($event))
        ->postJson('/api/management/schedule', ['title' => 'Hack'])
        ->assertStatus(405);
});

it('rejects reading the schedule with a foreign event header', function () {
    $owner = User::factory()->create(['is_approved' => true]);
    $event = Event::factory()->for($owner, 'owner')->create();
    scheduleItem($event, 'Ceremony', 1);
    $foreign = Event::factory()->create();

    test()->withToken(managementTokenFor($owner, $event))
        ->withHeaders(managementHeaders($foreign))
        ->getJson('/api/management/schedule')
        ->assertForbidden();
});
