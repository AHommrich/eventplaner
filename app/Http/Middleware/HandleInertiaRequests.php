<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

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
        if (!$user) return null;

        $events = $user->accessibleEvents()->get();
        if ($events->isEmpty()) return null;

        $sessionId = $request->session()->get('active_event_id');
        $event = $sessionId ? $events->firstWhere('id', $sessionId) : null;
        $event ??= $events->first();

        $request->session()->put('active_event_id', $event->id);

        return [
            'id'                 => $event->id,
            'name'               => $event->name,
            'user_id'            => $event->user_id,
            'drink_game_enabled' => (bool) $event->drink_game_enabled,
        ];
    }

    private function resolveAccessibleEvents(Request $request): array
    {
        $user = $request->user();
        if (!$user) return [];

        return $user->accessibleEvents()->get(['id', 'name'])->toArray();
    }

    private function resolveUserEventRequests(Request $request): array
    {
        $user = $request->user();
        if (!$user || $user->isAdmin()) return [];

        return \App\Models\EventRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'declined'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'event_name', 'status', 'created_at'])
            ->toArray();
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
            'active_event'        => $this->resolveActiveEvent($request),
            'accessible_events'   => $this->resolveAccessibleEvents($request),
            'user_event_requests' => $this->resolveUserEventRequests($request),
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
