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
            return response()->json(['message' => 'Dein Zugang zur Getränke-Statistik wurde deaktiviert.'], 403);
        }

        return $next($request);
    }
}
