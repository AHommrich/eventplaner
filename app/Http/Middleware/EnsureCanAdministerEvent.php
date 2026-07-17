<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for administer-level routes that resolve the event from the session
 * (no {event} route binding to hang a `can:administer,event` on).
 *
 * Mirrors {@see \App\Http\Controllers\Controller::activeEvent()} resolution, then
 * requires the user to pass EventPolicy::administer (event_admin ∪ owner ∪
 * superadmin). Runs inside the `has_event` group, so an accessible event always
 * exists by the time this runs; a manager (or lower) hitting it gets a 403.
 */
class EnsureCanAdministerEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $event = $this->resolveActiveEvent($request);

        if (! $event || ! $user?->canAdminister($event)) {
            abort(403);
        }

        return $next($request);
    }

    private function resolveActiveEvent(Request $request): ?Event
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $events = $user->accessibleEvents()->get();
        if ($events->isEmpty()) {
            return null;
        }

        $sessionId = $request->session()->get('active_event_id');
        $event = $sessionId ? $events->firstWhere('id', $sessionId) : null;

        return $event ?? $events->first();
    }
}
