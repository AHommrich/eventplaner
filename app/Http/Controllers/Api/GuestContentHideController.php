<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\GuestContentHide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-managed hide list: the caller hides every future photo uploaded by
 * another guest of the same event. Owner uploads (photos.guest_id IS NULL)
 * are structurally out of scope — the hidden_guest_id FK only accepts a
 * guests row.
 *
 * Backs the App-Store-Guideline-1.2 requirement that users can silence
 * abusive uploaders without contacting a moderator first.
 */
class GuestContentHideController extends Controller
{
    public function store(Request $request, Guest $guest): JsonResponse
    {
        $viewer = $request->user();

        abort_if($guest->id === $viewer->id, 422, 'You cannot hide your own content.');
        abort_if($guest->event_id !== $viewer->event_id, 422, 'Target guest is not in your event.');

        $hide = GuestContentHide::firstOrCreate([
            'viewer_guest_id' => $viewer->id,
            'hidden_guest_id' => $guest->id,
        ], [
            'event_id' => $viewer->event_id,
        ]);

        return response()->json([
            'hidden_guest_id' => $hide->hidden_guest_id,
        ], $hide->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Guest $guest): JsonResponse
    {
        $viewer = $request->user();

        GuestContentHide::where('viewer_guest_id', $viewer->id)
            ->where('hidden_guest_id', $guest->id)
            ->delete();

        return response()->json(null, 204);
    }

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $hidden = GuestContentHide::where('viewer_guest_id', $viewer->id)
            ->with('hiddenGuest:id,firstname,lastname')
            ->get()
            ->map(fn (GuestContentHide $h) => [
                'id' => $h->hidden_guest_id,
                'firstname' => $h->hiddenGuest?->firstname,
                'lastname' => $h->hiddenGuest?->lastname,
            ])
            ->filter(fn (array $row) => $row['firstname'] !== null)
            ->values();

        return response()->json(['hidden_guests' => $hidden]);
    }
}
