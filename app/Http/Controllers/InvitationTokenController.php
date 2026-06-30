<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Support\Str;

/**
 * Generates and regenerates invitation tokens for groups and solo guests.
 *
 * Token = 32-char random string embedded in the QR code of the invitation and
 * checked by {@see \App\Http\Controllers\Api\QrAuthController} for authentication.
 *
 *  - `generate()`          → one token per group and per solo guest (idempotent via updateOrCreate)
 *  - `generateForGroup()`  → single regeneration of a group (old token is replaced)
 *  - `generateForGuest()`  → single regeneration of a solo guest
 *
 * Cross-event guard via `$this->activeEvent()->id` comparison (403 otherwise).
 */
class InvitationTokenController extends Controller
{
    public function generate()
    {
        $event = $this->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Kein aktives Event.');
        }

        // one token per group in the event
        $event->groups()->each(function ($group) {
            InvitationToken::updateOrCreate(
                ['group_id' => $group->id],
                ['token' => Str::random(32), 'guest_id' => null],
            );
        });

        // one token per solo guest in the event
        $event->guests()->whereNull('group_id')->each(function ($guest) {
            InvitationToken::updateOrCreate(
                ['guest_id' => $guest->id],
                ['token' => Str::random(32), 'group_id' => null],
            );
        });

        return redirect()->back()->with('success', 'QR-Codes wurden generiert.');
    }

    public function generateForGroup(Group $group)
    {
        $event = $this->activeEvent();
        abort_if($group->event_id !== $event?->id, 403);

        InvitationToken::updateOrCreate(
            ['group_id' => $group->id],
            ['token' => Str::random(32), 'guest_id' => null],
        );

        return redirect()->back()->with('success', 'QR-Code wurde generiert.');
    }

    public function generateForGuest(Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        InvitationToken::updateOrCreate(
            ['guest_id' => $guest->id],
            ['token' => Str::random(32), 'group_id' => null],
        );

        return redirect()->back()->with('success', 'QR-Code wurde generiert.');
    }
}
