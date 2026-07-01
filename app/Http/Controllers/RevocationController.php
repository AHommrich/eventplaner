<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevocationController extends Controller
{
    /**
     * GET /revocations
     * Shows all guests with declined_pending for the active event.
     */
    public function index(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $guests = Guest::where('event_id', $event->id)
            ->where('rsvp_status', 'declined_pending')
            ->with(['group', 'rsvpSetByGuest', 'rsvpSetByUser'])
            ->orderBy('rsvp_set_at', 'desc')
            ->get()
            ->map(fn (Guest $g) => [
                'id' => $g->id,
                'firstname' => $g->firstname,
                'lastname' => $g->lastname,
                'group_name' => $g->group?->name,
                'rsvp_set_at' => $g->rsvp_set_at?->toIso8601String(),
                'set_by_guest' => $g->rsvpSetByGuest
                    ? ['id' => $g->rsvpSetByGuest->id, 'firstname' => $g->rsvpSetByGuest->firstname, 'lastname' => $g->rsvpSetByGuest->lastname]
                    : null,
                'set_by_user' => $g->rsvpSetByUser
                    ? ['id' => $g->rsvpSetByUser->id, 'name' => $g->rsvpSetByUser->name]
                    : null,
            ]);

        return Inertia::render('Revocations/Index', [
            'guests' => $guests,
        ]);
    }

    /**
     * POST /revocations/{guest}/approve
     * Approves the revocation request → rsvp_status = null.
     */
    public function approve(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'declined_pending', 422);

        $guest->update([
            'rsvp_status' => null,
            'rsvp_set_by_guest_id' => null,
            'rsvp_set_by_user_id' => $request->user()->id,
            'rsvp_set_at' => now(),
        ]);

        return redirect()->route('revocations.index');
    }

    /**
     * POST /revocations/{guest}/decline
     * Declines the request → rsvp_status stays declined.
     */
    public function decline(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'declined_pending', 422);

        $guest->update([
            'rsvp_status' => 'declined',
            'rsvp_set_by_user_id' => $request->user()->id,
            'rsvp_set_at' => now(),
        ]);

        return redirect()->route('revocations.index');
    }
}
