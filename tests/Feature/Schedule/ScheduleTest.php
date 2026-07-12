<?php

use App\Models\Event;
use App\Models\ScheduleItem;

it('shows the schedule index page', function () {
    actingAsOwner();

    $this->get(route('schedule.index'))->assertOk();
});

it('creates a station for the active event with an appended sort order', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    ScheduleItem::create(['event_id' => $event->id, 'title' => 'First', 'sort_order' => 0]);

    $this->post(route('schedule.store'), [
        'title' => 'Registry office',
        'starts_at' => '12:00',
        'location_city' => 'Koblenz',
        'location_lat' => 50.3569,
        'location_lng' => 7.5890,
    ])->assertRedirect();

    $created = ScheduleItem::where('title', 'Registry office')->first();
    expect($created)->not->toBeNull();
    expect($created->event_id)->toBe($event->id);
    expect($created->sort_order)->toBe(1);
    expect($created->location_city)->toBe('Koblenz');
});

it('rejects a station without a title', function () {
    actingAsOwner();

    $this->post(route('schedule.store'), ['starts_at' => '12:00'])
        ->assertSessionHasErrors('title');
});

it('rejects an invalid time format', function () {
    actingAsOwner();

    $this->post(route('schedule.store'), ['title' => 'X', 'starts_at' => '25:99'])
        ->assertSessionHasErrors('starts_at');
});

it('updates a station of the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $item = ScheduleItem::create(['event_id' => $event->id, 'title' => 'Old', 'sort_order' => 0]);

    $this->patch(route('schedule.update', $item), ['title' => 'New', 'starts_at' => '19:00'])
        ->assertRedirect();

    $item->refresh();
    expect($item->title)->toBe('New');
    expect((string) $item->starts_at)->toContain('19:00');
});

it('reorders stations of the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $a = ScheduleItem::create(['event_id' => $event->id, 'title' => 'A', 'sort_order' => 0]);
    $b = ScheduleItem::create(['event_id' => $event->id, 'title' => 'B', 'sort_order' => 1]);

    $this->patch(route('schedule.reorder'), ['ids' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($a->fresh()->sort_order)->toBe(1);
    expect($b->fresh()->sort_order)->toBe(0);
});

it('deletes a station of the active event', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $item = ScheduleItem::create(['event_id' => $event->id, 'title' => 'Gone', 'sort_order' => 0]);

    $this->delete(route('schedule.destroy', $item))->assertRedirect();

    expect(ScheduleItem::find($item->id))->toBeNull();
});

it('does not leak stations of another event into the index', function () {
    $user = actingAsOwner();
    $ownEvent = $user->ownedEvents()->first();
    ScheduleItem::create(['event_id' => $ownEvent->id, 'title' => 'Mine', 'sort_order' => 0]);

    $otherEvent = Event::factory()->create();
    ScheduleItem::create(['event_id' => $otherEvent->id, 'title' => 'Theirs', 'sort_order' => 0]);

    $this->get(route('schedule.index'))
        ->assertInertia(fn ($page) => $page->has('items', 1)->where('items.0.title', 'Mine'));
});

it('forbids editing a station that belongs to another event', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreign = ScheduleItem::create(['event_id' => $otherEvent->id, 'title' => 'Theirs', 'sort_order' => 0]);

    $this->patch(route('schedule.update', $foreign), ['title' => 'Hacked'])->assertNotFound();
    $this->delete(route('schedule.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh()->title)->toBe('Theirs');
});
