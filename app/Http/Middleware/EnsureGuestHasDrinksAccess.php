<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Additional barrier for drink-tracking endpoints (`/api/drinks*`).
 *
 * Sits behind {@see EnsureGuestHasAppAccess}: the guest does have app access, but
 * the organizer can disable the drinking game per guest (e.g.
 * children, pregnant guests). JSON 403 with code `drinks_blocked` on refusal.
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
