<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RevocationRequestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Self-service endpoints for the logged-in guest (React Native app).
 *
 *  - GET  /me                          → own profile + family members with RSVP status
 *  - POST /rsvp                        → own accept/decline as `*_pending` (organizer confirms)
 *  - POST /{guestId}/rsvp              → set for a family member; actor must have accepted themselves
 *  - POST /rsvp/revoke                 → revocation request for a final decline (declined → revocation_requested)
 *
 * State machine: all guest inputs land in `*_pending`; only the organizer
 * promotes to `accepted` / `declined`. Final states can no longer be changed
 * by the guest — revocation only via the explicit /revoke request.
 *
 * RSVP deadline is checked in {@see self::assertDeadlineNotPassed()}.
 */
class GuestApiController extends Controller
{
    /**
     * GET /api/guest/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();
        $guest->load(['group.guests.rsvpSetByGuest', 'rsvpSetByGuest', 'rsvpSetByUser']);

        $groupMembers = [];
        if ($guest->group_id && $guest->group) {
            $groupMembers = $guest->group->guests
                ->where('id', '!=', $guest->id)
                ->map(fn ($m) => $this->formatGuest($m))
                ->values();
        }

        return response()->json([
            'guest_id' => $guest->id,
            'firstname' => $guest->firstname,
            'lastname' => $guest->lastname,
            'type' => $guest->group_id ? 'family' : 'solo',
            'family_name' => $guest->group?->name,
            'rsvp_status' => $guest->rsvp_status,
            'rsvp_set_by' => $this->formatSetter($guest),
            'group_members' => $groupMembers,
        ]);
    }

    /**
     * POST /api/guest/rsvp
     * Guest sets their own answer → pending states.
     */
    public function rsvp(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();

        $this->assertDeadlineNotPassed($guest);
        // guest may only change if not already in a final state (accepted/declined)
        abort_if(
            in_array($guest->rsvp_status, ['accepted', 'declined']),
            422,
            'Dein Status wurde bereits final gesetzt. Bitte kontaktiere den Veranstalter.'
        );

        $request->validate(['attending' => 'required|boolean']);

        $guest->update([
            'rsvp_status' => $request->boolean('attending') ? 'accepted_pending' : 'declined_pending',
            'rsvp_set_by_guest_id' => $guest->id,
            'rsvp_set_by_user_id' => null,
            'rsvp_set_at' => now(),
        ]);

        return response()->json(['rsvp_status' => $guest->rsvp_status]);
    }

    /**
     * POST /api/guest/{guest_id}/rsvp
     * Set RSVP for another guest in the family.
     * Allowed when the actor is themselves accepted_pending or accepted.
     */
    public function rsvpForMember(Request $request, int $guestId): JsonResponse
    {
        /** @var \App\Models\Guest $actor */
        $actor = $request->user();

        abort_if(
            ! in_array($actor->rsvp_status, ['accepted_pending', 'accepted']),
            403,
            'Du musst selbst zugesagt haben, um andere Familienmitglieder anzumelden.'
        );

        $target = \App\Models\Guest::findOrFail($guestId);

        abort_if(
            $target->group_id !== $actor->group_id || $actor->group_id === null,
            403,
            'Dieses Familienmitglied gehört nicht zu deiner Gruppe.'
        );

        $this->assertDeadlineNotPassed($actor);

        abort_if(
            in_array($target->rsvp_status, ['accepted', 'declined']),
            422,
            'Der Status dieses Gastes wurde bereits final gesetzt.'
        );

        $request->validate(['attending' => 'required|boolean']);

        $target->update([
            'rsvp_status' => $request->boolean('attending') ? 'accepted_pending' : 'declined_pending',
            'rsvp_set_by_guest_id' => $actor->id,
            'rsvp_set_by_user_id' => null,
            'rsvp_set_at' => now(),
        ]);

        return response()->json([
            'guest_id' => $target->id,
            'rsvp_status' => $target->rsvp_status,
        ]);
    }

    /**
     * POST /api/guest/rsvp/revoke
     * Guest requests revocation of a final decline (declined → declined_pending).
     */
    public function revoke(Request $request): JsonResponse
    {
        /** @var \App\Models\Guest $guest */
        $guest = $request->user();

        abort_if(
            ! in_array($guest->rsvp_status, ['declined', 'declined_pending']),
            422,
            'Nur abgesagte Gäste können eine Rücknahme beantragen.'
        );

        $guest->update([
            'rsvp_status' => 'revocation_requested',
            'rsvp_set_at' => now(),
        ]);

        // email to event owner
        $guest->load('event.owner');
        if ($guest->event?->owner?->email) {
            Mail::to($guest->event->owner->email)
                ->send(new RevocationRequestMail($guest, $guest->event));
        }

        return response()->json(['rsvp_status' => 'revocation_requested']);
    }

    // --- Helpers ---

    private function formatGuest(\App\Models\Guest $g): array
    {
        return [
            'guest_id' => $g->id,
            'firstname' => $g->firstname,
            'lastname' => $g->lastname,
            'rsvp_status' => $g->rsvp_status,
            'rsvp_set_by' => $this->formatSetter($g),
        ];
    }

    private function formatSetter(\App\Models\Guest $g): ?array
    {
        if ($g->rsvp_set_by_guest_id) {
            $setter = $g->rsvpSetByGuest;

            return $setter ? ['guest_id' => $setter->id, 'firstname' => $setter->firstname, 'lastname' => $setter->lastname] : null;
        }
        if ($g->rsvp_set_by_user_id) {
            $setter = $g->rsvpSetByUser;

            return $setter ? ['user_id' => $setter->id, 'name' => $setter->name] : null;
        }

        return null;
    }

    private function assertDeadlineNotPassed(\App\Models\Guest $guest): void
    {
        $deadline = $guest->event?->rsvp_deadline;
        if ($deadline && now()->isAfter($deadline)) {
            throw ValidationException::withMessages([
                'deadline' => 'Die Frist zur Änderung deiner Zusage ist abgelaufen.',
            ])->status(422);
        }
    }
}
