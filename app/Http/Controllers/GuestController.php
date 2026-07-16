<?php

namespace App\Http\Controllers;

use App\Models\DrinkLog;
use App\Models\FoodSpecial;
use App\Models\Group;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * CRUD + admin actions for the guests of an event.
 *
 * Organizer view (Inertia): create / edit / delete, manual RSVP setting
 * (without deadline check, in contrast to {@see \App\Http\Controllers\Api\GuestApiController}),
 * toggles for `app_access` and `drinks_access`, reset of a guest's app login
 * and drink logs.
 *
 * Cross-event guard: all mutating endpoints explicitly verify that the
 * target guest belongs to the active event (403 otherwise).
 */
class GuestController extends Controller
{
    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $data = $request->validate($this->referenceRules($event?->id));

        if (! empty($data['group_id']) && empty($data['lastname'])) {
            $group = Group::find($data['group_id']);
            if ($group) {
                $data['lastname'] = $group->name;
            }
        }

        $guest = Guest::create([
            'event_id' => $event?->id,
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'] ?? '',
            'group_id' => $data['group_id'] ?? null,
        ]);

        $guest->foodSpecials()->sync($data['food_specials'] ?? []);

        return redirect()->back()->with('success', 'Gast erstellt!');
    }

    public function destroy(Guest $guest)
    {
        abort_if($guest->event_id !== $this->activeEvent()?->id, 403);

        $guest->delete();

        return redirect()->back()->with('success', 'Gast wurde gelöscht.');
    }

    public function edit(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

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
        $guestData['is_active'] = $this->hasActiveAppToken($guest);

        $qrToken = $guest->getQrToken();
        $qrUrl = $qrToken ? url('/api/auth/qr/'.$qrToken->token) : null;

        return Inertia::render('Guests/Edit', [
            'guest' => $guestData,
            'qr_url' => $qrUrl,
            'groups' => $event ? $event->groups()->with(['guests' => fn ($q) => $q->select('id', 'group_id', 'firstname')])->orderBy('name')->get(['id', 'name']) : collect(),
            'food_specials' => FoodSpecial::where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event?->id))
                ->orderBy('name')->get(['id', 'name', 'translation_key']),
        ]);
    }

    public function update(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $data = $request->validate($this->referenceRules($event?->id));

        $guest->update([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'] ?? '',
            'group_id' => $data['group_id'],
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
     * Admin / event owner sets RSVP manually — ignores deadline.
     */
    public function adminRsvp(Request $request, Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $data = $request->validate([
            'rsvp_status' => 'nullable|in:accepted_pending,accepted,declined_pending,declined,revocation_requested',
        ]);

        $guest->update([
            'rsvp_status' => $data['rsvp_status'] ?? null,
            'rsvp_set_by_guest_id' => null,
            'rsvp_set_by_user_id' => $request->user()->id,
            'rsvp_set_at' => now(),
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

    public function resetAppLogin(Guest $guest)
    {
        $event = $this->activeEvent();
        abort_if($guest->event_id !== $event?->id, 403);

        $this->deleteAppTokens($guest);

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

    /**
     * Validation rules shared by store/update. Group and food-special
     * references are scoped to the active event so a cross-event id is rejected
     * (a hole route-gating does NOT catch):
     *   - groups have no templates → strict own-event existence.
     *   - food_specials must ALSO accept the global templates (event_id = null),
     *     valid for every event; other events' private rows stay rejected.
     */
    private function referenceRules(?int $eventId): array
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('event_id', $eventId)],
            'food_specials' => 'nullable|array',
            'food_specials.*' => [
                Rule::exists('food_specials', 'id')->where(
                    fn ($q) => $q->where('event_id', $eventId)->orWhereNull('event_id')
                ),
            ],
        ];
    }

    private function hasActiveAppToken(Guest $guest): bool
    {
        return PersonalAccessToken::where('tokenable_type', Guest::class)
            ->where('tokenable_id', $guest->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function deleteAppTokens(Guest $guest): void
    {
        PersonalAccessToken::where('tokenable_type', Guest::class)
            ->where('tokenable_id', $guest->id)
            ->delete();
    }
}
