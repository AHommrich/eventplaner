<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $appUrl = rtrim(config('app.url'), '/');

        $groups = Group::with(['guests', 'invitationToken'])->get()
            ->map(fn(Group $g) => [
                'id'     => $g->id,
                'name'   => $g->name,
                'guests' => $g->guests->map(fn($guest) => $guest->firstname . ' ' . $guest->lastname),
                'token'  => $g->invitationToken?->token,
                'qr_url' => $g->invitationToken
                    ? "{$appUrl}/api/auth/qr/{$g->invitationToken->token}"
                    : null,
            ]);

        $soloGuests = Guest::whereNull('group_id')->with('invitationToken')->get()
            ->map(fn(Guest $g) => [
                'id'     => $g->id,
                'name'   => $g->firstname . ' ' . $g->lastname,
                'token'  => $g->invitationToken?->token,
                'qr_url' => $g->invitationToken
                    ? "{$appUrl}/api/auth/qr/{$g->invitationToken->token}"
                    : null,
            ]);

        return Inertia::render('Invitations/Index', [
            'groups'     => $groups,
            'soloGuests' => $soloGuests,
        ]);
    }
}
