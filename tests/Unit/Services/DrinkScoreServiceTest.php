<?php

use App\Models\DrinkCatalog;
use App\Services\DrinkScoreService;

/**
 * Reine Funktionstests — keine DB, DrinkCatalog wird ohne save() instanziert.
 */

function catalog(array $attrs = []): DrinkCatalog
{
    return new DrinkCatalog(array_merge([
        'category'        => 'beer',
        'type'            => 'pils',
        'display_name'    => 'Pils',
        'alcohol_percent' => 5.0,
        'is_alcoholic'    => true,
        'negative_points' => 0,
        'is_active'       => true,
    ], $attrs));
}

it('scores a beer by volume × abv × 10', function () {
    $points = DrinkScoreService::basePoints(catalog(['alcohol_percent' => 5.0]), 0.5);
    expect($points)->toBe(25); // 0.5 × 5 × 10
});

it('scores wine higher than beer per volume', function () {
    $wine = catalog(['category' => 'wine', 'alcohol_percent' => 12.0]);
    $points = DrinkScoreService::basePoints($wine, 0.2);
    expect($points)->toBe(24); // 0.2 × 12 × 10
});

it('applies shot multiplier to spirits', function () {
    $shot = catalog(['category' => 'spirit', 'alcohol_percent' => 40.0]);
    $points = DrinkScoreService::basePoints($shot, 0.04);
    expect($points)->toBe(32); // 0.04 × 40 × 10 × 2.0 (SHOT_MULTIPLIER)
});

it('does not apply shot multiplier to non-spirit alcoholic drinks', function () {
    $longdrink = catalog(['category' => 'longdrink', 'alcohol_percent' => 7.0]);
    $points = DrinkScoreService::basePoints($longdrink, 0.25);
    expect($points)->toBe(18); // round(0.25 × 7 × 10) = 18, KEIN ×2
});

it('applies binge penalty after three consecutive alcoholic drinks', function () {
    $history = collect([
        ['is_alcoholic' => true],
        ['is_alcoholic' => true],
        ['is_alcoholic' => true],
    ]);
    $points = DrinkScoreService::effectivePoints(catalog(['alcohol_percent' => 5.0]), 0.5, $history);
    expect($points)->toBe(13); // 25 × 0.5 = 12.5 → round = 13
});

it('does not apply binge penalty when interleaved with nonalcoholic drinks', function () {
    $history = collect([
        ['is_alcoholic' => true],
        ['is_alcoholic' => false],  // unterbricht den Streak
        ['is_alcoholic' => true],
        ['is_alcoholic' => true],
    ]);
    $points = DrinkScoreService::effectivePoints(catalog(['alcohol_percent' => 5.0]), 0.5, $history);
    expect($points)->toBe(25); // voller Basis-Score
});

it('returns flat negative points for water', function () {
    $water = catalog(['category' => 'water', 'is_alcoholic' => false, 'negative_points' => -5]);
    $points = DrinkScoreService::basePoints($water, 0.5);
    expect($points)->toBe(-5);
});

it('returns flat negative points for softdrinks regardless of volume', function () {
    $cola = catalog(['category' => 'softdrink', 'is_alcoholic' => false, 'negative_points' => -3]);
    expect(DrinkScoreService::basePoints($cola, 0.2))->toBe(-3);
    expect(DrinkScoreService::basePoints($cola, 1.0))->toBe(-3);
});

it('streak counts consecutive alcoholic entries from the head', function () {
    $history = collect([
        ['is_alcoholic' => true],
        ['is_alcoholic' => true],
        ['is_alcoholic' => false],
        ['is_alcoholic' => true],
    ]);
    expect(DrinkScoreService::currentStreak($history))->toBe(2);
});

it('streak is zero when latest drink is nonalcoholic', function () {
    $history = collect([
        ['is_alcoholic' => false],
        ['is_alcoholic' => true],
        ['is_alcoholic' => true],
    ]);
    expect(DrinkScoreService::currentStreak($history))->toBe(0);
});
