<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Guest;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $event  = $this->activeEvent();
        $appUrl = rtrim(config('app.url'), '/');

        $groups = $event
            ? $event->groups()->with(['guests', 'invitationToken'])->get()
                ->map(fn(Group $g) => [
                    'id'     => $g->id,
                    'name'   => $g->name,
                    'guests' => $g->guests->map(fn($guest) => $guest->firstname . ' ' . $guest->lastname),
                    'token'  => $g->invitationToken?->token,
                    'qr_url' => $g->invitationToken ? "{$appUrl}/api/auth/qr/{$g->invitationToken->token}" : null,
                ])
            : collect();

        $soloGuests = $event
            ? $event->guests()->whereNull('group_id')->with('invitationToken')->get()
                ->map(fn(Guest $g) => [
                    'id'     => $g->id,
                    'name'   => $g->firstname . ' ' . $g->lastname,
                    'token'  => $g->invitationToken?->token,
                    'qr_url' => $g->invitationToken ? "{$appUrl}/api/auth/qr/{$g->invitationToken->token}" : null,
                ])
            : collect();

        return Inertia::render('Invitations/Index', [
            'groups'     => $groups,
            'soloGuests' => $soloGuests,
        ]);
    }
}
