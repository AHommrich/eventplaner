<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Verwaltet eingehende Gast-Anfragen (Rücknahmen, ggf. weitere Typen).
 */
class RequestController extends Controller
{
    /**
     * GET /requests
     * Zeigt alle offenen Anfragen für das aktive Event.
     */
    public function index(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $revocations = Guest::where('event_id', $event->id)
            ->where('rsvp_status', 'revocation_requested')
            ->with(['group', 'rsvpSetByGuest', 'rsvpSetByUser'])
            ->orderBy('rsvp_set_at', 'desc')
            ->get()
            ->map(fn (Guest $g) => [
                'id'           => $g->id,
                'type'         => 'revocation',
                'firstname'    => $g->firstname,
                'lastname'     => $g->lastname,
                'group_name'   => $g->group?->name,
                'rsvp_set_at'  => $g->rsvp_set_at?->toIso8601String(),
                'set_by_guest' => $g->rsvpSetByGuest
                    ? ['id' => $g->rsvpSetByGuest->id, 'firstname' => $g->rsvpSetByGuest->firstname, 'lastname' => $g->rsvpSetByGuest->lastname]
                    : null,
                'set_by_user'  => $g->rsvpSetByUser
                    ? ['id' => $g->rsvpSetByUser->id, 'name' => $g->rsvpSetByUser->name]
                    : null,
            ]);

        return Inertia::render('Requests/Index', [
            'revocations' => $revocations,
            // Hier später weitere Anfrage-Typen ergänzen
        ]);
    }

    /**
     * POST /requests/revocations/{guest}/approve
     * Rücknahme freigeben → rsvp_status = null (Gast kann erneut antworten).
     */
    public function approveRevocation(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'revocation_requested', 422);

        $guest->update([
            'rsvp_status'          => null,
            'rsvp_set_by_guest_id' => null,
            'rsvp_set_by_user_id'  => $request->user()->id,
            'rsvp_set_at'          => now(),
        ]);

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/revocations/{guest}/decline
     * Rücknahme ablehnen → bleibt declined.
     */
    public function declineRevocation(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'revocation_requested', 422);

        $guest->update([
            'rsvp_status'          => 'declined',
            'rsvp_set_by_user_id'  => $request->user()->id,
            'rsvp_set_at'          => now(),
        ]);

        return redirect()->route('requests.index');
    }
}
