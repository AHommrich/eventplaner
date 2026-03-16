<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureGuestHasDrinksAccess
{
    public function handle(Request $request, Closure $next)
    {
        $guest = $request->user();

        if ($guest && !$guest->drinks_access) {
            return response()->json(['message' => 'Dein Zugang zum Getränke-Tracking wurde deaktiviert.', 'code' => 'drinks_blocked'], 403);
        }

        return $next($request);
    }
}
