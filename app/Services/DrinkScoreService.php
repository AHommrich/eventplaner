<?php

namespace App\Services;

use App\Models\DrinkCatalog;
use App\Models\DrinkLog;
use Illuminate\Support\Collection;

/**
 * Calculates points for tracked drinks of the drinking game.
 *
 * Alcoholic formula:   round(amount_liter × alcohol_percent × 10)
 * Shot multiplier:     spirit category × {@see self::SHOT_MULTIPLIER}
 *                      (4 cl neat hits harder than 4 cl in a long drink)
 * Binge penalty:       from {@see self::BINGE_STREAK_THRESHOLD} alcoholic
 *                      drinks in a row → 50 % of base points
 * Non-alcoholic:       flat `negative_points` (water typically −5, softdrinks −3)
 *
 * The multipliers are chosen empirically, not clinically — the game is
 * entertainment, not a diagnostic tool.
 *
 * Pure-function design: all methods are static and side-effect-free
 * (except {@see self::guestHistory()}, which reads the DB). Hence unit-testable without DB.
 */
class DrinkScoreService
{
    /** Consecutive alcoholic drinks before binge penalty kicks in. */
    public const BINGE_STREAK_THRESHOLD = 3;

    /** Cooldown in seconds between drink logs per guest. */
    public const COOLDOWN_SECONDS = 60;

    /** Multiplier for shots (category = spirit) — shots hit faster. */
    public const SHOT_MULTIPLIER = 2.0;

    /**
     * Base points for a drink (without streak penalty).
     * Alcoholic: round((amount_liter * alcohol_percent) * 10)
     *   → shots (spirit) additionally receive ×SHOT_MULTIPLIER
     * Non-alcoholic: negative_points (flat value)
     *
     * @param  float  $amountLiter  Serving size in liters (from the selected size)
     */
    public static function basePoints(DrinkCatalog $catalog, float $amountLiter): int
    {
        if (! $catalog->is_alcoholic) {
            return $catalog->negative_points ?? 0;
        }

        $points = ($amountLiter * $catalog->alcohol_percent) * 10;

        if ($catalog->category === 'spirit') {
            $points *= self::SHOT_MULTIPLIER;
        }

        return (int) round($points);
    }

    /**
     * Effective points after streak penalty.
     * Non-alcoholic drinks always stay at the flat value.
     * For alcoholic drinks: if streak >= THRESHOLD → 50% of base points.
     *
     * @param  float  $amountLiter  Serving size in liters
     * @param  Collection  $history  Ordered log history of the guest (newest first),
     *                               each entry must contain 'is_alcoholic'
     */
    public static function effectivePoints(DrinkCatalog $catalog, float $amountLiter, Collection $history): int
    {
        $base = self::basePoints($catalog, $amountLiter);

        if (! $catalog->is_alcoholic) {
            return $base;
        }

        $streak = self::currentStreak($history);

        if ($streak >= self::BINGE_STREAK_THRESHOLD) {
            return (int) round($base * 0.5);
        }

        return $base;
    }

    /**
     * Calculates the current streak (consecutive alcoholic drinks
     * from the end of the history).
     *
     * @param  Collection  $history  Ordered log history (newest first),
     *                               each entry must contain 'is_alcoholic'
     */
    public static function currentStreak(Collection $history): int
    {
        $streak = 0;
        foreach ($history as $entry) {
            if ($entry['is_alcoholic']) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Loads the log history of a guest for streak calculation.
     * Returns a collection (newest first) with 'is_alcoholic' per entry.
     */
    public static function guestHistory(int $guestId): Collection
    {
        return DrinkLog::where('guest_id', $guestId)
            ->join('drinks', 'drink_logs.drink_id', '=', 'drinks.id')
            ->join('drink_catalog', 'drinks.drink_catalog_id', '=', 'drink_catalog.id')
            ->select('drink_logs.id', 'drink_catalog.is_alcoholic')
            ->orderByDesc('drink_logs.created_at')
            ->get()
            ->map(fn ($row) => ['is_alcoholic' => (bool) $row->is_alcoholic]);
    }
}
