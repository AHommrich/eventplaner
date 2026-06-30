<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Zusätzliche Schranke für Getränke-Tracking-Endpoints (`/api/drinks*`).
 *
 * Liegt hinter {@see EnsureGuestHasAppAccess}: der Gast hat zwar App-Zugang, aber
 * der Veranstalter kann das Trinkspiel pro Gast einzeln deaktivieren (Bsp.:
 * Kinder, schwangere Gäste). JSON-403 mit Code `drinks_blocked` bei Verweigerung.
 */
class EnsureGuestHasDrinksAccess
{
    public function handle(Request $request, Closure $next)
    {
        $guest = $request->user();

        if ($guest && ! $guest->drinks_access) {
            return response()->json(['message' => 'Dein Zugang zum Getränke-Tracking wurde deaktiviert.', 'code' => 'drinks_blocked'], 403);
        }

        return $next($request);
    }
}
