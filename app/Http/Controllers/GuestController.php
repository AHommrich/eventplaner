<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\Guest;
use App\Models\GuestDrink;
use App\Models\Group;
use App\Models\Category;
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
            'category_id'     => 'required|exists:categories,id',
            'group_id'        => 'nullable|exists:groups,id',
            'beer'            => 'boolean',
            'beer_thirst'     => 'nullable|integer|min:0|max:10',
            'wine'            => 'boolean',
            'wine_thirst'     => 'nullable|integer|min:0|max:10',
            'likelihood'      => 'required|in:sure,likely,maybe,unlikely,no',
            'invite'          => 'boolean',
            'food_specials'   => 'nullable|array',
            'food_specials.*' => 'exists:food_specials,id',
        ]);

        // Nachname aus Gruppe setzen, wenn leer
        if (!empty($data['group_id']) && empty($data['lastname'])) {
            $group = Group::find($data['group_id']);
            if ($group) {
                $data['lastname'] = $group->name;
            }
        }

        $guest = Guest::create([
            'firstname'   => $data['firstname'],
            'lastname'    => $data['lastname'] ?? '',
            'category_id' => $data['category_id'],
            'group_id'    => $data['group_id'] ?? null,
            'beer'        => $data['beer'] ?? false,
            'wine'        => $data['wine'] ?? false,
            'likelihood'  => $data['likelihood'] ?? 'maybe',
            'invite'      => $data['invite'] ?? false,
        ]);

        if (!empty($data['beer']) && !empty($data['beer_thirst']) && $data['beer_thirst'] > 0) {
            $guest->drinks()->create(['drink_type' => 'beer', 'thirst_level' => $data['beer_thirst']]);
        }
        if (!empty($data['wine']) && !empty($data['wine_thirst']) && $data['wine_thirst'] > 0) {
            $guest->drinks()->create(['drink_type' => 'wine', 'thirst_level' => $data['wine_thirst']]);
        }

        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        return redirect()->back()->with('success', 'Gast erstellt!');
    }

    public function destroy(Guest $guest)
    {
        $guest->delete();
        return redirect()->back()->with('success', 'Gast wurde gelöscht.');
    }

    public function edit(Request $request, Guest $guest)
    {
        $guest->load('category', 'group.invitationToken', 'drinks', 'foodSpecials', 'invitationToken');

        $returnTo = $request->query('return_to', url()->previous());
        if ($returnTo && Str::startsWith($returnTo, url('/'))) {
            session(['return_to' => $returnTo]);
        }

        $guestData = $guest->toArray();
        $guestData['food_specials'] = $guest->foodSpecials->pluck('id');

        $qrToken = $guest->getQrToken();
        $qrUrl = $qrToken ? url('/api/auth/qr/' . $qrToken->token) : null;

        return Inertia::render('Guests/Edit', [
            'guest'         => $guestData,
            'qr_url'        => $qrUrl,
            'categories'    => Category::orderBy('title', 'asc')->get(['id', 'title']),
            'groups'        => Group::orderBy('name', 'asc')->get(['id', 'name']),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'category_id'     => 'nullable|exists:categories,id',
            'group_id'        => 'nullable|exists:groups,id',
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
            'firstname'   => $data['firstname'],
            'lastname'    => $data['lastname'] ?? '',
            'category_id' => $data['category_id'],
            'group_id'    => $data['group_id'],
            'beer'        => $data['beer'] ?? false,
            'wine'        => $data['wine'] ?? false,
            'likelihood'  => $data['likelihood'],
            'invite'      => $data['invite'] ?? false,
        ]);

        $guest->drinks()->whereIn('drink_type', ['beer', 'wine'])->delete();
        if (!empty($data['beer']) && !empty($data['beer_thirst']) && $data['beer_thirst'] > 0) {
            $guest->drinks()->create(['drink_type' => 'beer', 'thirst_level' => $data['beer_thirst']]);
        }
        if (!empty($data['wine']) && !empty($data['wine_thirst']) && $data['wine_thirst'] > 0) {
            $guest->drinks()->create(['drink_type' => 'wine', 'thirst_level' => $data['wine_thirst']]);
        }

        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        $returnTo = session()->pull('return_to');
        if ($returnTo && Str::startsWith($returnTo, url('/'))) {
            return redirect()->to($returnTo)->with('success', 'Gast erfolgreich aktualisiert.');
        }

        return redirect()->route('table')->with('success', 'Gast erfolgreich aktualisiert.');
    }
}
