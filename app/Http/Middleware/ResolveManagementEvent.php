<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ManagementSessionResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Resolve an API request's active event and re-authorize its bearer token.
 *
 * Management tokens can outlive account or membership changes, so every
 * event-scoped request repeats the complete account, ability, membership,
 * and policy-tier checks. Controllers read the resolved Event from the
 * `management_event` request attribute instead of a web session.
 */
class ResolveManagementEvent
{
    public function __construct(private readonly ManagementSessionResolver $sessions) {}

    public function handle(Request $request, Closure $next, string $tier = 'manage')
    {
        $user = $request->user();
        $pairing = $this->sessions->resolve($request);
        $event = $pairing?->event;

        if (! $user instanceof User
            || ! $pairing
            || ! $event) {
            return response()->json(['message' => 'Management access is forbidden.'], 403);
        }

        if (! in_array($tier, ['manage', 'administer'], true)) {
            return response()->json(['message' => 'Management access is forbidden.'], 403);
        }

        $eventId = $request->header('X-Event-ID');
        if (! is_numeric($eventId)
            || (int) $eventId !== $event->id
            || ! Gate::forUser($user)->allows($tier, $event)) {
            return response()->json(['message' => 'Management access is forbidden.'], 403);
        }

        $request->attributes->set('management_pairing', $pairing);
        $request->attributes->set('management_event', $event);

        return $next($request);
    }
}
