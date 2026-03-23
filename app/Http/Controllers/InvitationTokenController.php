<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Support\Str;

class InvitationTokenController extends Controller
{
    public function generate()
    {
        $event = $this->activeEvent();
        if (!$event) return redirect()->back()->with('error', 'Kein aktives Event.');

        // Token pro Gruppe im Event
        $event->groups()->each(function ($group) {
            InvitationToken::updateOrCreate(
                ['group_id' => $group->id],
                ['token' => Str::random(32), 'guest_id' => null],
            );
        });

        // Token pro Solo-Gast im Event
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
