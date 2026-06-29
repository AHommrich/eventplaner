<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Schützt API-Routen, die nur Gästen mit `app_access = true` offenstehen.
 *
 * Der Veranstalter kann pro Gast den App-Zugang abschalten (Bsp.: nicht-eingeladene
 * Begleitperson). Greift erst NACH `auth:sanctum`, prüft also einen bereits
 * authentifizierten Gast — JSON-403 mit Code `app_blocked` bei Verweigerung.
 */
class EnsureGuestHasAppAccess
{
    public function handle(Request $request, Closure $next)
    {
        $guest = $request->user();

        if ($guest && !$guest->app_access) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.', 'code' => 'app_blocked'], 403);
        }

        return $next($request);
    }
}
