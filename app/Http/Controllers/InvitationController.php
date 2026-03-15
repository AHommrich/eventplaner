<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Guest;
use App\Models\InvitationToken;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        $appUrl = rtrim(config('app.url'), '/');

        // Familien-Einladungen
        $families = Family::with(['guests', 'invitationToken'])->get()
            ->map(fn(Family $f) => [
                'id'      => $f->id,
                'name'    => $f->name,
                'guests'  => $f->guests->map(fn($g) => $g->firstname . ' ' . $g->lastname),
                'token'   => $f->invitationToken?->token,
                'qr_url'  => $f->invitationToken
                    ? "{$appUrl}/api/auth/qr/{$f->invitationToken->token}"
                    : null,
            ]);

        // Solo-Gäste (ohne Familie)
        $soloGuests = Guest::whereNull('family_id')->with('invitationToken')->get()
            ->map(fn(Guest $g) => [
                'id'     => $g->id,
                'name'   => $g->firstname . ' ' . $g->lastname,
                'token'  => $g->invitationToken?->token,
                'qr_url' => $g->invitationToken
                    ? "{$appUrl}/api/auth/qr/{$g->invitationToken->token}"
                    : null,
            ]);

        return Inertia::render('Invitations/Index', [
            'families'   => $families,
            'soloGuests' => $soloGuests,
        ]);
    }
}
