<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Verwaltet eingehende Anfragen (Rücknahmen, Event-Anfragen, ggf. weitere Typen).
 */
class RequestController extends Controller
{
    /**
     * GET /requests
     */
    public function index(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        // Rücknahme-Anfragen (declined → will zurück zu open)
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
                'rsvp_status'  => $g->rsvp_status,
                'rsvp_set_at'  => $g->rsvp_set_at?->toIso8601String(),
                'set_by_guest' => $g->rsvpSetByGuest
                    ? ['id' => $g->rsvpSetByGuest->id, 'firstname' => $g->rsvpSetByGuest->firstname, 'lastname' => $g->rsvpSetByGuest->lastname]
                    : null,
                'set_by_user'  => $g->rsvpSetByUser
                    ? ['id' => $g->rsvpSetByUser->id, 'name' => $g->rsvpSetByUser->name]
                    : null,
            ]);

        // Event-Anfragen (nur für Admin sichtbar)
        $eventRequests = [];
        if (auth()->user()->isAdmin()) {
            $eventRequests = EventRequest::where('status', 'pending')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (EventRequest $er) => [
                    'id'         => $er->id,
                    'user_name'  => $er->user->name,
                    'user_email' => $er->user->email,
                    'event_name' => $er->event_name,
                    'created_at' => $er->created_at->toIso8601String(),
                ]);
        }

        return Inertia::render('Requests/Index', [
            'revocations'   => $revocations,
            'event_requests' => $eventRequests,
        ]);
    }

    /**
     * POST /requests/revocations/{guest}/approve
     * Rücknahme freigeben → rsvp_status = null
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
     * Rücknahme ablehnen → bleibt declined
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

    /**
     * POST /requests/event-requests/{eventRequest}/approve
     * Event-Anfrage genehmigen → Event erstellen
     */
    public function approveEventRequest(Request $request, EventRequest $eventRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($eventRequest->status !== 'pending', 422);

        $event = Event::create([
            'user_id' => $eventRequest->user_id,
            'name'    => $eventRequest->event_name,
        ]);

        $eventRequest->delete();

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/event-requests/{eventRequest}/decline
     * Event-Anfrage ablehnen
     */
    public function declineEventRequest(Request $request, EventRequest $eventRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($eventRequest->status !== 'pending', 422);

        $eventRequest->update(['status' => 'declined']);

        return redirect()->route('requests.index');
    }
}
