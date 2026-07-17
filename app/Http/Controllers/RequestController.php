<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Guest;
use App\Models\PhotoReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Manages incoming requests (revocations, event requests, possibly other types).
 */
class RequestController extends Controller
{
    /**
     * GET /requests
     */
    public function index(Request $request)
    {
        $event = $this->activeEvent();
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        // non-admin strictly needs an active event
        abort_if(! $event && ! $isAdmin, 404);

        // Photo reports are adjudicated at administer level. Managers handle
        // revocations but do not see guest complaints they cannot act on.
        $canSeePhotoReports = $isAdmin || ($event && $user->canAdminister($event));

        // revocation requests (only if an event exists)
        $revocations = $event ? Guest::where('event_id', $event->id)
            ->where('rsvp_status', 'revocation_requested')
            ->with(['group', 'rsvpSetByGuest', 'rsvpSetByUser'])
            ->orderBy('rsvp_set_at', 'desc')
            ->get()
            ->map(fn (Guest $g) => [
                'id' => $g->id,
                'type' => 'revocation',
                'firstname' => $g->firstname,
                'lastname' => $g->lastname,
                'group_name' => $g->group?->name,
                'rsvp_status' => $g->rsvp_status,
                'rsvp_set_at' => $g->rsvp_set_at?->toIso8601String(),
                'set_by_guest' => $g->rsvpSetByGuest
                    ? ['id' => $g->rsvpSetByGuest->id, 'firstname' => $g->rsvpSetByGuest->firstname, 'lastname' => $g->rsvpSetByGuest->lastname]
                    : null,
                'set_by_user' => $g->rsvpSetByUser
                    ? ['id' => $g->rsvpSetByUser->id, 'name' => $g->rsvpSetByUser->name]
                    : null,
            ]) : collect();

        // event requests (only visible to admin)
        $eventRequests = [];
        if (auth()->user()->isAdmin()) {
            $eventRequests = EventRequest::where('status', 'pending')
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (EventRequest $er) => [
                    'id' => $er->id,
                    'user_name' => $er->user->name,
                    'user_email' => $er->user->email,
                    'event_name' => $er->event_name,
                    'created_at' => $er->created_at->toIso8601String(),
                ]);
        }

        // photo reports (App Store Guideline 1.2). Sysadmin sees them across
        // all events; owners/event_admins are scoped to the active event; managers
        // do not see them at all.
        $photoReports = collect();
        if ($canSeePhotoReports) {
            $photoReportsQuery = PhotoReport::query()
                ->where('status', 'open')
                ->with(['photo.album', 'reporter', 'reportedGuest', 'event']);
            if (! $isAdmin) {
                $photoReportsQuery->where('event_id', $event->id);
            }
            $photoReports = $photoReportsQuery
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (PhotoReport $r) => [
                    'id' => $r->id,
                    'type' => 'photo_report',
                    'event_id' => $r->event_id,
                    'event_name' => $isAdmin ? $r->event?->name : null,
                    'photo' => [
                        'id' => $r->photo_id,
                        'url' => $r->photo?->url,
                        'album_slug' => $r->photo?->album?->slug,
                    ],
                    'reporter' => $r->reporter ? [
                        'id' => $r->reporter->id,
                        'firstname' => $r->reporter->firstname,
                        'lastname' => $r->reporter->lastname,
                    ] : null,
                    'reported_uploader' => $r->reportedGuest ? [
                        'id' => $r->reportedGuest->id,
                        'firstname' => $r->reportedGuest->firstname,
                        'lastname' => $r->reportedGuest->lastname,
                    ] : null,
                    'reason' => $r->reason,
                    'message' => $r->message,
                    'created_at' => $r->created_at->toIso8601String(),
                ]);
        }

        return Inertia::render('Requests/Index', [
            'revocations' => $revocations,
            'event_requests' => $eventRequests,
            'photo_reports' => $photoReports,
        ]);
    }

    /**
     * POST /requests/revocations/{guest}/approve
     * Approve revocation → rsvp_status = null
     */
    public function approveRevocation(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'revocation_requested', 422);

        $guest->update([
            'rsvp_status' => null,
            'rsvp_set_by_guest_id' => null,
            'rsvp_set_by_user_id' => $request->user()->id,
            'rsvp_set_at' => now(),
        ]);

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/revocations/{guest}/decline
     * Decline revocation → stays declined
     */
    public function declineRevocation(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);
        abort_if($guest->rsvp_status !== 'revocation_requested', 422);

        $guest->update([
            'rsvp_status' => 'declined',
            'rsvp_set_by_user_id' => $request->user()->id,
            'rsvp_set_at' => now(),
        ]);

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/event-requests/{eventRequest}/approve
     * Approve event request → create event
     */
    public function approveEventRequest(Request $request, EventRequest $eventRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($eventRequest->status !== 'pending', 422);

        $event = Event::create([
            'user_id' => $eventRequest->user_id,
            'name' => $eventRequest->event_name,
        ]);

        EventController::createDefaultAlbums($event->id);

        $eventRequest->delete();

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/event-requests/{eventRequest}/decline
     * Decline event request
     */
    public function declineEventRequest(Request $request, EventRequest $eventRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($eventRequest->status !== 'pending', 422);

        $eventRequest->update(['status' => 'declined']);

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/photo-reports/{photoReport}/resolve
     * Close a photo report. Allowed to sysadmin or the owner of the event the
     * report belongs to.
     */
    public function resolvePhotoReport(Request $request, PhotoReport $photoReport)
    {
        $user = $request->user();
        abort_unless($photoReport->event && $user->canAdminister($photoReport->event), 403);
        abort_if($photoReport->status !== 'open', 422);

        $photoReport->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => $user->id,
        ]);

        return redirect()->route('requests.index');
    }

    /**
     * POST /requests/photo-reports/{photoReport}/delete-photo
     * Destructive shortcut: deletes the reported photo (object storage + DB)
     * and closes the report in one round-trip. Guarded by the same rule as
     * resolve. The frontend gates this with a double confirmation.
     */
    public function deletePhotoFromReport(Request $request, PhotoReport $photoReport)
    {
        $user = $request->user();
        abort_unless($photoReport->event && $user->canAdminister($photoReport->event), 403);
        abort_if($photoReport->status !== 'open', 422);

        $photo = $photoReport->photo;
        if ($photo) {
            $key = $photo->r2_key ?? ltrim((string) parse_url((string) $photo->url, PHP_URL_PATH), '/');
            if ($key !== '') {
                Storage::disk('s3')->delete($key);
            }
            $photo->delete();
        }

        $photoReport->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => $user->id,
        ]);

        return redirect()->route('requests.index');
    }
}
