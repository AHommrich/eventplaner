<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the main app — user needs at least one accessible event.
 *
 * Accessible means: user is owner (`events.user_id = $user->id`) OR co-organizer
 * (`event_user` pivot). Admins skip the check. On refusal redirect
 * to `/no-event`, from where the user can request an event (EventRequest).
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
