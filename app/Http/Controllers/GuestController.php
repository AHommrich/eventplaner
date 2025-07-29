<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\GuestDrink;
use App\Models\Family;
use App\Models\Badge;
use App\Models\FoodSpecial;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GuestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'badge_id'        => 'required|exists:badges,id',
            'family_id'       => 'nullable|exists:families,id',
            'beer'            => 'boolean',
            'beer_thirst'     => 'nullable|integer|min:0|max:10',
            'wine'            => 'boolean',
            'wine_thirst'     => 'nullable|integer|min:0|max:10',
            'likelihood'      => 'required|in:sure,likely,maybe,unlikely,no',
            'invite'          => 'boolean',
            'food_specials'   => 'nullable|array',
            'food_specials.*' => 'exists:food_specials,id',
        ]);

        // Nachname aus Familie setzen, wenn leer
        if (!empty($data['family_id']) && empty($data['lastname'])) {
            $family = Family::find($data['family_id']);
            if ($family) {
                $data['lastname'] = $family->name;
            }
        }

        // Gast anlegen
        $guest = Guest::create([
            'firstname'  => $data['firstname'],
            'lastname'   => $data['lastname'] ?? '',
            'badge_id'   => $data['badge_id'],
            'family_id'  => $data['family_id'] ?? null,
            'beer'       => $data['beer'] ?? false,
            'wine'       => $data['wine'] ?? false,
            'likelihood' => $data['likelihood'] ?? 'maybe',
            'invite'     => $data['invite'] ?? false,
        ]);

        // Drinks speichern
        if (!empty($data['beer']) && !empty($data['beer_thirst']) && $data['beer_thirst'] > 0) {
            $guest->drinks()->create([
                'drink_type'   => 'beer',
                'thirst_level' => $data['beer_thirst'],
            ]);
        }
        if (!empty($data['wine']) && !empty($data['wine_thirst']) && $data['wine_thirst'] > 0) {
            $guest->drinks()->create([
                'drink_type'   => 'wine',
                'thirst_level' => $data['wine_thirst'],
            ]);
        }

        // Food Specials
        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        return redirect()->back()->with('success', 'Gast erstellt!');
    }

    public function destroy(Guest $guest)
    {
        $guest->delete(); // Cascade löscht Drinks + Pivot
        return redirect()->back()->with('success', 'Gast wurde gelöscht.');
    }

    public function edit(Guest $guest)
    {
        $guest->load('badge', 'family', 'drinks', 'foodSpecials');

        // Wichtig: food_specials nur als IDs ins Frontend
        $guestData = $guest->toArray();
        $guestData['food_specials'] = $guest->foodSpecials->pluck('id');

        return Inertia::render('Guests/Edit', [
            'guest'         => $guestData,
            'badges'        => Badge::orderBy('title', 'asc')->get(['id', 'title']),
            'families'      => Family::orderBy('name', 'asc')->get(['id', 'name']),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'badge_id'        => 'nullable|exists:badges,id',
            'family_id'       => 'nullable|exists:families,id',
            'beer'            => 'boolean',
            'beer_thirst'     => 'nullable|integer|min:0|max:10',
            'wine'            => 'boolean',
            'wine_thirst'     => 'nullable|integer|min:0|max:10',
            'likelihood'      => 'required|in:sure,likely,maybe,unlikely,no',
            'invite'          => 'boolean',
            'food_specials'   => 'nullable|array',
            'food_specials.*' => 'exists:food_specials,id',
        ]);

        $guest->update([
            'firstname'  => $data['firstname'],
            'lastname'   => $data['lastname'] ?? '',
            'badge_id'   => $data['badge_id'],
            'family_id'  => $data['family_id'],
            'beer'       => $data['beer'] ?? false,
            'wine'       => $data['wine'] ?? false,
            'likelihood' => $data['likelihood'],
            'invite'     => $data['invite'] ?? false,
        ]);

        // Drinks aktualisieren
        $guest->drinks()->where('drink_type', 'beer')->delete();
        $guest->drinks()->where('drink_type', 'wine')->delete();
        if (!empty($data['beer']) && !empty($data['beer_thirst']) && $data['beer_thirst'] > 0) {
            $guest->drinks()->create([
                'drink_type'   => 'beer',
                'thirst_level' => $data['beer_thirst'],
            ]);
        }
        if (!empty($data['wine']) && !empty($data['wine_thirst']) && $data['wine_thirst'] > 0) {
            $guest->drinks()->create([
                'drink_type'   => 'wine',
                'thirst_level' => $data['wine_thirst'],
            ]);
        }

        // Food Specials
        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        return redirect()->route('table')->with('success', 'Gast erfolgreich aktualisiert.');
    }
}
