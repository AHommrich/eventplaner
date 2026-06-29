<?php

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use App\Models\DrinkLog;
use App\Models\Event;
use App\Models\Guest;

function makeDrink(Event $event, array $catalogAttrs = [], float $amount = 0.5): Drink
{
    $catalog = DrinkCatalog::factory()->create($catalogAttrs);
    $size = DrinkCatalogSize::create([
        'catalog_id'   => $catalog->id,
        'amount_liter' => $amount,
        'is_default'   => true,
        'sort_order'   => 0,
    ]);
    return Drink::create([
        'event_id'         => $event->id,
        'drink_catalog_id' => $catalog->id,
        'size_id'          => $size->id,
    ]);
}

it('returns drinks grouped by catalog with selected sizes', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    makeDrink($event, ['display_name' => 'Pils']);
    makeDrink($event, ['display_name' => 'Wasser', 'category' => 'water', 'is_alcoholic' => false, 'negative_points' => -5]);

    $response = actingAsGuest($guest)->getJson('/api/drinks')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0'))->toHaveKeys(['display_name', 'category', 'sizes']);
});

it('logs a drink with size_id and amount_liter', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $drink = makeDrink($event, [], 0.5);

    actingAsGuest($guest)->postJson('/api/drinks/log', ['drink_id' => $drink->id])
        ->assertStatus(201)
        ->assertJsonStructure(['drink_id', 'base_points', 'final_points', 'points_total']);

    expect(DrinkLog::where('guest_id', $guest->id)->count())->toBe(1);
    $log = DrinkLog::first();
    expect((float) $log->amount_liter)->toBe(0.5);
});

it('uses DrinkScoreService to compute base_points (0.5l beer × 5% × 10 = 25)', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $drink = makeDrink($event, ['alcohol_percent' => 5.0], 0.5);

    actingAsGuest($guest)->postJson('/api/drinks/log', ['drink_id' => $drink->id])
        ->assertStatus(201)
        ->assertJsonPath('base_points', 25)
        ->assertJsonPath('final_points', 25);
});

it('returns 403 when drink belongs to a different event', function () {
    $event       = Event::factory()->create();
    $otherEvent  = Event::factory()->create();
    $guest       = Guest::factory()->create(['event_id' => $event->id]);
    $foreignDrink = makeDrink($otherEvent);

    actingAsGuest($guest)->postJson('/api/drinks/log', ['drink_id' => $foreignDrink->id])
        ->assertStatus(403);
});

it('returns stats including totals and leaderboard', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->create(['event_id' => $event->id]);
    $drink = makeDrink($event);

    DrinkLog::create([
        'guest_id'     => $guest->id,
        'drink_id'     => $drink->id,
        'size_id'      => $drink->size_id,
        'amount_liter' => 0.5,
        'base_points'  => 25,
        'final_points' => 25,
    ]);

    actingAsGuest($guest)->getJson('/api/drinks/stats')
        ->assertOk()
        ->assertJsonStructure(['my_stats', 'event_totals', 'leaderboard', 'guest_totals', 'current_streak', 'binge_penalty', 'cooldown_seconds']);
});

it('rejects access without drinks_access middleware', function () {
    $event = Event::factory()->create();
    $guest = Guest::factory()->withoutDrinksAccess()->create(['event_id' => $event->id]);

    actingAsGuest($guest)->postJson('/api/drinks/log', ['drink_id' => 1])
        ->assertStatus(403)
        ->assertJson(['code' => 'drinks_blocked']);
});
