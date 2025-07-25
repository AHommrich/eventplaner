<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\GuestDrink;
use App\Models\Family;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname'    => 'required|string|max:255',
            'lastname'     => 'nullable|string|max:255',
            'badge_id'     => 'required|exists:badges,id',
            'family_id'    => 'nullable|exists:families,id',
            'beer'         => 'boolean',
            'beer_thirst'  => 'nullable|integer|min:0|max:10',
            'wine'         => 'boolean',
            'wine_thirst'  => 'nullable|integer|min:0|max:10',
                'likelihood'  => 'required|in:sure,likely,maybe,unlikely,no',

        ]);

        // Falls eine Familie gewählt wurde und kein Nachname übergeben wurde,
        // setzen wir den Namen der Familie als Nachname
        if (!empty($data['family_id']) && empty($data['lastname'])) {
            $family = Family::find($data['family_id']);
            if ($family) {
                $data['lastname'] = $family->name;
            }
        }

        // Gast anlegen
        $guest = Guest::create([
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'] ?? '',
            'badge_id'  => $data['badge_id'],
            'family_id' => $data['family_id'] ?? null,
            'beer'      => $data['beer'] ?? false,
            'wine'      => $data['wine'] ?? false,
            'likelihood' => $data['likelihood'] ?? 'maybe',
        ]);

        // Drinks für Gast anlegen
        if (!empty($data['beer']) && !empty($data['beer_thirst']) && $data['beer_thirst'] > 0) {
            GuestDrink::create([
                'guest_id'     => $guest->id,
                'drink_type'   => 'beer',
                'thirst_level' => $data['beer_thirst'],
            ]);
        }

        if (!empty($data['wine']) && !empty($data['wine_thirst']) && $data['wine_thirst'] > 0) {
            GuestDrink::create([
                'guest_id'     => $guest->id,
                'drink_type'   => 'wine',
                'thirst_level' => $data['wine_thirst'],
            ]);
        }

        return redirect()->back()->with('success', 'Gast erstellt!');
    }

    public function destroy(Guest $guest)
    {
        $guest->delete(); // Löscht auch GuestDrinks (wenn FK mit cascade)
        return redirect()->back()->with('success', 'Gast wurde gelöscht.');
    }
}
