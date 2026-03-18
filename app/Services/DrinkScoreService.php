<?php

namespace App\Services;

use App\Models\DrinkCatalog;

class DrinkScoreService
{
    /**
     * Basispunkte für ein Getränk aus dem Katalog.
     * Alkoholisch: round((amount_liter * alcohol_percent) * 10)
     * Alkoholfrei: negative_points (Flat-Wert, z. B. -5 oder -3)
     */
    public static function basePoints(DrinkCatalog $catalog): int
    {
        if (!$catalog->is_alcoholic) {
            return $catalog->negative_points ?? 0;
        }

        return (int) round(($catalog->amount_liter * $catalog->alcohol_percent) * 10);
    }
}
