<?php

use App\Models\Event;
use App\Models\Group;
use App\Models\Guest;
use App\Models\ScheduleItem;

/**
 * @return array{0: ScheduleItem, 1: ScheduleItem, 2: ScheduleItem}
 */
function stationsFor(Event $event): array
{
    return [
        ScheduleItem::create(['event_id' => $event->id, 'title' => 'Registry office', 'starts_at' => '12:00', 'sort_order' => 0]),
        ScheduleItem::create(['event_id' => $event->id, 'title' => 'Lunch', 'starts_at' => '13:30', 'sort_order' => 1]),
        ScheduleItem::create(['event_id' => $event->id, 'title' => 'Party', 'starts_at' => '19:00', 'sort_order' => 2]),
    ];
}

it('returns all stations to a guest without a group', function () {
    $event = Event::factory()->create();
    stationsFor($event);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => null]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonCount(3, 'schedule_stations')
        ->assertJsonPath('schedule_stations.0.title', 'Registry office')
        ->assertJsonPath('schedule_stations.0.starts_at', '12:00');
});

it('returns all stations to a group with nothing hidden', function () {
    $event = Event::factory()->create();
    stationsFor($event);
    $group = Group::factory()->create(['event_id' => $event->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonCount(3, 'schedule_stations');
});

it('hides the stations in a group hidden set', function () {
    $event = Event::factory()->create();
    [$registry, $lunch, $party] = stationsFor($event);
    $group = Group::factory()->create(['event_id' => $event->id]);
    $group->hiddenScheduleItems()->sync([$registry->id, $party->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonCount(1, 'schedule_stations')
        ->assertJsonPath('schedule_stations.0.title', 'Lunch');
});

it('supports a gap: sees ceremony and party but not the lunch between', function () {
    $event = Event::factory()->create();
    [$registry, $lunch, $party] = stationsFor($event);
    $group = Group::factory()->create(['event_id' => $event->id]);
    $group->hiddenScheduleItems()->sync([$lunch->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonCount(2, 'schedule_stations')
        ->assertJsonPath('schedule_stations.0.title', 'Registry office')
        ->assertJsonPath('schedule_stations.1.title', 'Party');
});

it('assembles the station address and exposes coordinates', function () {
    $event = Event::factory()->create();
    ScheduleItem::create([
        'event_id' => $event->id,
        'title' => 'Registry office',
        'starts_at' => '12:00',
        'location_street' => 'Willi-Hörter-Platz',
        'location_house_number' => '1',
        'location_postal_code' => '56068',
        'location_city' => 'Koblenz',
        'location_country' => 'Deutschland',
        'location_lat' => 50.3536,
        'location_lng' => 7.5788,
        'sort_order' => 0,
    ]);
    $guest = Guest::factory()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->getJson('/api/event/info')
        ->assertOk()
        ->assertJsonPath('schedule_stations.0.address', 'Willi-Hörter-Platz 1, 56068 Koblenz')
        ->assertJsonPath('schedule_stations.0.lat', 50.3536)
        ->assertJsonPath('schedule_stations.0.lng', 7.5788);
});
