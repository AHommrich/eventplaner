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
        // Token pro Gruppe
        Group::all()->each(function (Group $group) {
            InvitationToken::updateOrCreate(
                ['group_id' => $group->id],
                ['token' => Str::random(32), 'guest_id' => null],
            );
        });

        // Token pro Solo-Gast (ohne Gruppe)
        Guest::whereNull('group_id')->each(function (Guest $guest) {
            InvitationToken::updateOrCreate(
                ['guest_id' => $guest->id],
                ['token' => Str::random(32), 'group_id' => null],
            );
        });

        return redirect()->back()->with('success', 'QR-Codes wurden generiert.');
    }
}
