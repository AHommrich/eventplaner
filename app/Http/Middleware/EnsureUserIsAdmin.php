<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schützt Admin-Routen (z. B. `/admin/users`) — nur User mit `role = 'admin'`.
 *
 * Bei Verweigerung wird **nicht** mit 403 abgewiesen, sondern zur Onboarding-Seite
 * umgeleitet (Endusern soll nicht sichtbar werden, dass eine Admin-Sektion existiert).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
