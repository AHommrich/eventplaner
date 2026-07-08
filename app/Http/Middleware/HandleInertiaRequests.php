<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

/**
 * Shares global props with all Inertia pages.
 *
 * Important shared keys:
 *  - `auth.user`            — logged-in user or null
 *  - `active_event`         — session-based active event (see {@see \App\Http\Controllers\Controller::activeEvent()})
 *  - `accessible_events`    — all events the user has access to (owner + co-organizer); sidebar shows switcher when >1
 *  - `user_event_requests`  — pending/declined event access requests for the onboarding flow (non-admins only)
 *  - `pending_notifications` — per-type open counts (revocations, event_requests, photo_reports, total) for sidebar badge + dashboard card
 *  - `ziggy`                — serialized route definitions for the frontend helper
 *  - `sidebarOpen`          — cookie-persisted sidebar state
 *
 * The active event is resolved on every request so that stale session IDs
 * (event deleted, access revoked) are corrected automatically.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    private function resolveActiveEvent(Request $request): ?array
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
        $event ??= $events->first();

        $request->session()->put('active_event_id', $event->id);

        return [
            'id' => $event->id,
            'name' => $event->name,
            'user_id' => $event->user_id,
            'drink_game_enabled' => (bool) $event->drink_game_enabled,
            'photo_game_enabled' => (bool) $event->photo_game_enabled,
        ];
    }

    private function resolveAccessibleEvents(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        return $user->accessibleEvents()->get(['id', 'name'])->toArray();
    }

    private function resolveUserEventRequests(Request $request): array
    {
        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return [];
        }

        return \App\Models\EventRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'declined'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'event_name', 'status', 'created_at'])
            ->toArray();
    }

    /**
     * Per-type open-notification counts for the sidebar badge and the
     * dashboard card. Scope follows the /requests hub: revocations and photo
     * reports are event-scoped for owners, cross-event for sysadmin; event
     * requests are sysadmin-only.
     */
    private function resolvePendingNotifications(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['revocations' => 0, 'event_requests' => 0, 'photo_reports' => 0, 'total' => 0];
        }

        $isAdmin = $user->isAdmin();
        $activeEventId = $request->session()->get('active_event_id');

        $revocations = 0;
        $photoReports = 0;
        if ($activeEventId) {
            $revocations = \App\Models\Guest::where('event_id', $activeEventId)
                ->where('rsvp_status', 'revocation_requested')
                ->count();
        }
        if ($isAdmin) {
            $photoReports = \App\Models\PhotoReport::where('status', 'open')->count();
        } elseif ($activeEventId) {
            $photoReports = \App\Models\PhotoReport::where('status', 'open')
                ->where('event_id', $activeEventId)
                ->count();
        }

        $eventRequests = $isAdmin
            ? \App\Models\EventRequest::where('status', 'pending')->count()
            : 0;

        return [
            'revocations' => $revocations,
            'event_requests' => $eventRequests,
            'photo_reports' => $photoReports,
            'total' => $revocations + $eventRequests + $photoReports,
        ];
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'active_event' => $this->resolveActiveEvent($request),
            'accessible_events' => $this->resolveAccessibleEvents($request),
            'user_event_requests' => $this->resolveUserEventRequests($request),
            'pending_notifications' => $this->resolvePendingNotifications($request),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
