<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Protects API routes that are only available to guests with `app_access = true`.
 *
 * The organizer can disable app access per guest (e.g. uninvited
 * plus-one). Runs only AFTER `auth:sanctum`, so it checks an already
 * authenticated guest — JSON 403 with code `app_blocked` on refusal.
 */
class EnsureGuestHasAppAccess
{
    public function handle(Request $request, Closure $next)
    {
        $guest = $request->user();

        if ($guest && ! $guest->app_access) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.', 'code' => 'app_blocked'], 403);
        }

        return $next($request);
    }
}
