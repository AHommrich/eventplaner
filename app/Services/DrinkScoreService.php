<?php

namespace App\Services;

use App\Models\DrinkCatalog;
use App\Models\DrinkLog;
use Illuminate\Support\Collection;

class DrinkScoreService
{
    /** Consecutive alcoholic drinks before binge penalty kicks in. */
    public const BINGE_STREAK_THRESHOLD = 3;

    /** Cooldown in seconds between drink logs per guest. */
    public const COOLDOWN_SECONDS = 60;

    /**
     * Basispunkte für ein Getränk (ohne Streak-Penalty).
     * Alkoholisch: round((amount_liter * alcohol_percent) * 10)
     * Alkoholfrei: negative_points (Flat-Wert)
     */
    public static function basePoints(DrinkCatalog $catalog): int
    {
        if (!$catalog->is_alcoholic) {
            return $catalog->negative_points ?? 0;
        }

        return (int) round(($catalog->amount_liter * $catalog->alcohol_percent) * 10);
    }

    /**
     * Effektive Punkte nach Streak-Penalty.
     * Nicht-alkoholische Getränke bleiben immer beim Flat-Wert.
     * Bei alkoholischen Getränken: wenn Streak >= THRESHOLD → 50% der Basispunkte.
     *
     * @param DrinkCatalog $catalog  Das neue Getränk
     * @param Collection   $history  Geordnete Log-History des Gastes (neueste zuerst),
     *                               jeder Eintrag muss 'is_alcoholic' enthalten
     */
    public static function effectivePoints(DrinkCatalog $catalog, Collection $history): int
    {
        $base = self::basePoints($catalog);

        if (!$catalog->is_alcoholic) {
            return $base;
        }

        $streak = self::currentStreak($history);

        if ($streak >= self::BINGE_STREAK_THRESHOLD) {
            return (int) round($base * 0.5);
        }

        return $base;
    }

    /**
     * Berechnet den aktuellen Streak (aufeinanderfolgende alkoholische Getränke
     * vom Ende der History).
     *
     * @param Collection $history  Geordnete Log-History (neueste zuerst),
     *                             jeder Eintrag muss 'is_alcoholic' enthalten
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
     * Lädt die Log-History eines Gastes für Streak-Berechnung.
     * Gibt eine Collection zurück (neueste zuerst) mit 'is_alcoholic' pro Eintrag.
     */
    public static function guestHistory(int $guestId): Collection
    {
        return DrinkLog::where('guest_id', $guestId)
            ->join('drinks', 'drink_logs.drink_id', '=', 'drinks.id')
            ->join('drink_catalog', 'drinks.drink_catalog_id', '=', 'drink_catalog.id')
            ->select('drink_logs.id', 'drink_catalog.is_alcoholic')
            ->orderByDesc('drink_logs.created_at')
            ->get()
            ->map(fn($row) => ['is_alcoholic' => (bool) $row->is_alcoholic]);
    }
}
