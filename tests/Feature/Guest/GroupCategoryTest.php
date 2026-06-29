<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\FoodSpecial;
use App\Models\Group;
use App\Models\Guest;

it('creates a new group', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->post(route('groups.store'), ['name' => 'Familie Test'])
        ->assertRedirect();

    expect(Group::where('event_id', $event->id)->where('name', 'Familie Test')->exists())->toBeTrue();
});

it('returns JSON when group creation is requested with wantsJson()', function () {
    actingAsOwner();

    $this->postJson(route('groups.store'), ['name' => 'JSON Group'])
        ->assertOk()
        ->assertJsonStructure(['id', 'name']);
});

it('deletes a group from the active event', function () {
    $user  = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $group = Group::factory()->create(['event_id' => $event->id]);

    $this->delete(route('groups.destroy', $group))->assertRedirect();

    expect(Group::find($group->id))->toBeNull();
});

it('rejects deleting a group of another event', function () {
    actingAsOwner();
    $otherEvent = Event::factory()->create();
    $foreign    = Group::factory()->create(['event_id' => $otherEvent->id]);

    $this->delete(route('groups.destroy', $foreign))->assertStatus(403);
});

it('creates a new category', function () {
    actingAsOwner();

    $this->post(route('categories.store'), ['title' => 'VIP'])
        ->assertRedirect();

    expect(Category::where('title', 'VIP')->exists())->toBeTrue();
});

it('creates a food special', function () {
    actingAsOwner();

    $this->post(route('foodspecials.store'), ['name' => 'Vegetarisch'])
        ->assertRedirect();

    expect(FoodSpecial::where('name', 'Vegetarisch')->exists())->toBeTrue();
});

it('returns JSON when food special creation is requested with wantsJson()', function () {
    actingAsOwner();

    $this->postJson(route('foodspecials.store'), ['name' => 'Vegan'])
        ->assertOk()
        ->assertJsonStructure(['id', 'name']);
});
