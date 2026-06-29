<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\DrinkLog;
use App\Models\FoodSpecial;
use App\Models\Group;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * CRUD + Admin-Aktionen für Gäste eines Events.
 *
 * Veranstalter-Sicht (Inertia): Anlegen / Editieren / Löschen, manuelles RSVP-Setzen
 * (ohne Deadline-Check, im Gegensatz zu {@see \App\Http\Controllers\Api\GuestApiController}),
 * Toggles für `app_access` und `drinks_access`, Reset der Getränke-Logs eines Gastes.
 *
 * Cross-Event-Schutz: alle modifizierenden Endpoints prüfen explizit, dass der
 * Ziel-Gast zum aktiven Event gehört (403 sonst).
 */
class GuestController extends Controller
{
    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'group_id'        => 'nullable|exists:groups,id',
            'food_specials'   => 'nullable|array',
            'food_specials.*' => 'exists:food_specials,id',
        ]);

        if (!empty($data['group_id']) && empty($data['lastname'])) {
            $group = Group::find($data['group_id']);
            if ($group) $data['lastname'] = $group->name;
        }

        $guest = Guest::create([
            'event_id'  => $event?->id,
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'] ?? '',
            'group_id'  => $data['group_id'] ?? null,
        ]);

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
        $event = $this->activeEvent();

        $guest->load('group.invitationToken', 'foodSpecials', 'invitationToken', 'rsvpSetByGuest', 'rsvpSetByUser');

        $returnTo = $request->query('return_to', url()->previous());
        if ($returnTo && Str::startsWith($returnTo, url('/'))) {
            session(['return_to' => $returnTo]);
        }

        $guestData = $guest->toArray();
        $guestData['food_specials'] = $guest->foodSpecials->pluck('id');
        $guestData['rsvp_set_by_guest'] = $guest->rsvpSetByGuest
            ? ['id' => $guest->rsvpSetByGuest->id, 'firstname' => $guest->rsvpSetByGuest->firstname, 'lastname' => $guest->rsvpSetByGuest->lastname]
            : null;
        $guestData['rsvp_set_by_user'] = $guest->rsvpSetByUser
            ? ['id' => $guest->rsvpSetByUser->id, 'name' => $guest->rsvpSetByUser->name]
            : null;

        $qrToken = $guest->getQrToken();
        $qrUrl   = $qrToken ? url('/api/auth/qr/' . $qrToken->token) : null;

        return Inertia::render('Guests/Edit', [
            'guest'         => $guestData,
            'qr_url'        => $qrUrl,
            'groups'        => $event ? $event->groups()->with(['guests' => fn($q) => $q->select('id', 'group_id', 'firstname')])->orderBy('name')->get(['id', 'name']) : collect(),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name', 'translation_key']),
        ]);
    }

    public function update(Request $request, Guest $guest)
    {
        $data = $request->validate([
            'firstname'       => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'group_id'        => 'nullable|exists:groups,id',
            'food_specials'   => 'nullable|array',
            'food_specials.*' => 'exists:food_specials,id',
        ]);

        $guest->update([
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'] ?? '',
            'group_id'  => $data['group_id'],
        ]);

        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        $returnTo = session()->pull('return_to');
        if ($returnTo && Str::startsWith($returnTo, url('/'))) {
            return redirect()->to($returnTo)->with('success', 'Gast erfolgreich aktualisiert.');
        }

        return redirect()->route('guests.index')->with('success', 'Gast erfolgreich aktualisiert.');
    }

    /**
     * POST /guests/{guest}/rsvp
     * Admin/Event-Owner setzt RSVP manuell — ignoriert Deadline.
     */
    public function adminRsvp(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $data = $request->validate([
            'rsvp_status' => 'nullable|in:accepted_pending,accepted,declined_pending,declined,revocation_requested',
        ]);

        $guest->update([
            'rsvp_status'          => $data['rsvp_status'] ?? null,
            'rsvp_set_by_guest_id' => null,
            'rsvp_set_by_user_id'  => $request->user()->id,
            'rsvp_set_at'          => now(),
        ]);

        return redirect()->route('guests.edit', $guest->id);
    }

    public function updateAppAccess(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $data = $request->validate([
            'app_access' => 'required|boolean',
        ]);

        $guest->update(['app_access' => $data['app_access']]);

        return redirect()->route('guests.edit', $guest->id);
    }

    public function updateDrinksAccess(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $data = $request->validate([
            'drinks_access' => 'required|boolean',
        ]);

        $guest->update(['drinks_access' => $data['drinks_access']]);

        return redirect()->route('guests.edit', $guest->id);
    }

    public function resetDrinkLogs(Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        DrinkLog::where('guest_id', $guest->id)->delete();

        return redirect()->route('guests.edit', $guest->id);
    }
}
