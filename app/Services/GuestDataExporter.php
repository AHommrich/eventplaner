<?php

namespace App\Services;

use App\Models\DrinkLog;
use App\Models\Guest;
use App\Models\Photo;
use App\Models\PhotoGameAssignment;

/**
 * Builds the guest-side GDPR right-of-access payload (Art. 15 + Art. 20).
 *
 * Guest-scoped: only data belonging to the authenticated guest is returned.
 * Other family members are surfaced as name + RSVP only — no sensitive
 * fields — so the invoking guest can see what data they appear in on their
 * relatives' side without leaking the relatives' personal info.
 *
 * Output keys are stable once published — do not rename without bumping
 * FORMAT_VERSION.
 */
class GuestDataExporter
{
    public const FORMAT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(Guest $guest): array
    {
        $guest->loadMissing([
            'group.guests',
            'foodSpecials',
            'invitationToken',
            'photos',
        ]);

        return [
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'guest' => $this->serializeSelf($guest),
            'family_members' => $this->serializeFamilyMembers($guest),
            'photos' => $guest->photos->map(fn (Photo $p) => $this->serializePhoto($p))->all(),
            'drink_logs' => $this->serializeDrinkLogs($guest),
            'photo_game_submission' => $this->serializePhotoGameSubmission($guest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSelf(Guest $guest): array
    {
        return [
            'id' => $guest->id,
            'firstname' => $guest->firstname,
            'lastname' => $guest->lastname,
            'family_name' => $guest->group?->name,
            'rsvp_status' => $guest->rsvp_status,
            'rsvp_set_at' => optional($guest->rsvp_set_at)->toIso8601String(),
            'app_access' => (bool) $guest->app_access,
            'drinks_access' => (bool) $guest->drinks_access,
            'food_specials' => $guest->foodSpecials->pluck('name')->all(),
            'invitation_token' => $guest->invitationToken
                ? [
                    'token' => $guest->invitationToken->token,
                    'created_at' => optional($guest->invitationToken->created_at)->toIso8601String(),
                ]
                : null,
            'created_at' => optional($guest->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeFamilyMembers(Guest $guest): array
    {
        if (! $guest->group_id || ! $guest->group) {
            return [];
        }

        return $guest->group->guests
            ->where('id', '!=', $guest->id)
            ->map(fn (Guest $member) => [
                'id' => $member->id,
                'firstname' => $member->firstname,
                'lastname' => $member->lastname,
                'rsvp_status' => $member->rsvp_status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeDrinkLogs(Guest $guest): array
    {
        return DrinkLog::with('drink')
            ->where('guest_id', $guest->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (DrinkLog $log) => [
                'id' => $log->id,
                'drink_name' => $log->drink?->name,
                'amount_liter' => $log->amount_liter,
                'base_points' => $log->base_points,
                'final_points' => $log->final_points,
                'logged_at' => optional($log->created_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializePhotoGameSubmission(Guest $guest): ?array
    {
        $assignment = PhotoGameAssignment::with(['task', 'override', 'photo'])
            ->where('guest_id', $guest->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if (! $assignment) {
            return null;
        }

        $taskText = $assignment->override?->custom_text
            ?? $assignment->task?->description;

        return [
            'assignment' => $taskText,
            'photo_url' => $assignment->photo?->url,
            'submitted_at' => optional($assignment->submitted_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePhoto(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'url' => $photo->url,
            'description' => $photo->description,
            'uploaded_at' => optional($photo->created_at)->toIso8601String(),
        ];
    }
}
