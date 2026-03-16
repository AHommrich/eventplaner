<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
