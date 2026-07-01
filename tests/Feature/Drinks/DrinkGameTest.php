<?php

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use App\Models\DrinkLog;
use App\Models\Guest;

it('shows the drink game admin page', function () {
    actingAsOwner();

    $this->get(route('drinks.game'))->assertOk();
});

it('updates the drink game end time', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();

    $this->patch(route('drinks.game.update'), ['drink_game_end_time' => '2027-08-15 23:00:00'])
        ->assertRedirect();

    expect($event->fresh()->drink_game_end_time)->not->toBeNull();
});

it('clears the drink game end time when null is sent', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $event->update(['drink_game_end_time' => '2027-01-01 22:00:00']);

    $this->patch(route('drinks.game.update'), ['drink_game_end_time' => null])
        ->assertRedirect();

    expect($event->fresh()->drink_game_end_time)->toBeNull();
});

it('includes drink logs in the game leaderboard', function () {
    $user = actingAsOwner();
    $event = $user->ownedEvents()->first();
    $catalog = DrinkCatalog::factory()->create();
    $size = DrinkCatalogSize::create(['catalog_id' => $catalog->id, 'amount_liter' => 0.5, 'is_default' => true, 'sort_order' => 0]);
    $drink = Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalog->id, 'size_id' => $size->id]);
    $guest = Guest::factory()->create(['event_id' => $event->id, 'firstname' => 'Top']);

    DrinkLog::create(['guest_id' => $guest->id, 'drink_id' => $drink->id, 'size_id' => $size->id, 'amount_liter' => 0.5, 'base_points' => 25, 'final_points' => 25]);

    $response = $this->get(route('drinks.game'))->assertOk();
    $totals = $response->getOriginalContent()->getData()['page']['props']['event_totals'];
    expect($totals)->not->toBeEmpty();
});
