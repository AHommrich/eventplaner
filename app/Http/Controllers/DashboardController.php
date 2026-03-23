<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        return Inertia::render('Dashboard', [
            'stats' => [
                'guest_total'   => $event?->guests()->count() ?? 0,
                'rsvp_accepted' => $event?->guests()->whereIn('rsvp_status', ['accepted', 'accepted_pending'])->count() ?? 0,
                'rsvp_declined' => $event?->guests()->whereIn('rsvp_status', ['declined', 'declined_pending'])->count() ?? 0,
                'rsvp_open'     => $event?->guests()->whereNull('rsvp_status')->count() ?? 0,
                'photo_count'   => $event?->photos()->count() ?? 0,
            ],
        ]);
    }
}
