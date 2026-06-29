<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schützt die Hauptapp — User braucht mindestens ein zugängliches Event.
 *
 * Zugänglich heißt: User ist Owner (`events.user_id = $user->id`) ODER Mitveranstalter
 * (`event_user` Pivot). Admins überspringen die Prüfung. Bei Verweigerung Redirect
 * auf `/no-event`, von wo aus der User ein Event anfordern kann (EventRequest).
 */
class EnsureHasEventAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->accessibleEvents()->exists())) {
            return $next($request);
        }

        return redirect()->route('no-event');
    }
}
