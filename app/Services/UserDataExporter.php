<?php

namespace App\Services;

use App\Models\DrinkLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Note;
use App\Models\Photo;
use App\Models\User;

/**
 * Builds the GDPR right-of-access payload for a single user (Art. 15 + Art. 20).
 *
 * Returns a deeply nested associative array that the controller serialises to
 * JSON. Output keys are intentionally stable — once published they cannot be
 * renamed without breaking exports the user has already kept.
 *
 * Password hash, remember token, sessions, and any other user-internal data
 * are never included.
 */
class UserDataExporter
{
    public const FORMAT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'export_format_version' => self::FORMAT_VERSION,
            'user' => $user->only([
                'id', 'name', 'email', 'role', 'is_approved',
                'email_verified_at', 'privacy_accepted_at',
                'created_at', 'updated_at',
            ]),
            'events' => $user->ownedEvents()->with([
                'guests.foodSpecials',
                'guests.photos',
                'guests.invitationToken',
                'photos',
            ])->get()->map(fn (Event $event) => $this->serializeEvent($event))->all(),
            // Notes the user authored or was assigned — possibly on events they do
            // not own (as a manager). Includes soft-deleted rows (with a deleted_at
            // marker) until the retention pruner hard-purges them.
            'notes' => Note::withTrashed()
                ->where(fn ($q) => $q->where('author_user_id', $user->id)->orWhere('assignee_user_id', $user->id))
                ->get()
                ->map(fn (Note $n) => $this->serializeNote($n, $user))
                ->all(),
            // Pairing secrets and Sanctum bearer tokens are deliberately omitted;
            // the event binding is ordinary session metadata and remains visible.
            'device_pairings' => $user->devicePairings()
                ->get()
                ->map(fn ($pairing) => [
                    'id' => $pairing->id,
                    'event_id' => $pairing->event_id,
                    'device_label' => $pairing->device_label,
                    'expires_at' => optional($pairing->expires_at)->toIso8601String(),
                    'redeemed_at' => optional($pairing->redeemed_at)->toIso8601String(),
                    'created_at' => optional($pairing->created_at)->toIso8601String(),
                ])
                ->all(),
            'push_tokens' => $user->pushTokens()
                ->get()
                ->map(fn ($token) => [
                    'id' => $token->id,
                    'expo_token' => $token->expo_token,
                    'platform' => $token->platform,
                    'last_used_at' => optional($token->last_used_at)->toIso8601String(),
                    'created_at' => optional($token->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNote(Note $note, User $user): array
    {
        return [
            'id' => $note->id,
            'event_id' => $note->event_id,
            'type' => $note->type,
            'title' => $note->title,
            'body' => $note->body,
            'is_done' => (bool) $note->is_done,
            'done_at' => optional($note->done_at)->toIso8601String(),
            'relation' => $note->author_user_id === $user->id ? 'author' : 'assignee',
            'author_name' => $note->author_name,
            'created_at' => optional($note->created_at)->toIso8601String(),
            'deleted_at' => optional($note->deleted_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'date' => optional($event->date)->toDateString(),
            'rsvp_deadline' => optional($event->rsvp_deadline)->toDateString(),
            'venue' => [
                'name' => $event->venue_name,
                'street' => $event->venue_street,
                'house_number' => $event->venue_house_number,
                'postal_code' => $event->venue_postal_code,
                'city' => $event->venue_city,
                'country' => $event->venue_country,
            ],
            'guests' => $event->guests->map(fn (Guest $g) => $this->serializeGuest($g))->all(),
            'photos' => $event->photos->map(fn (Photo $p) => $this->serializePhoto($p))->all(),
            'created_at' => optional($event->created_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGuest(Guest $guest): array
    {
        $drinkLogs = DrinkLog::where('guest_id', $guest->id)->get();

        return [
            'id' => $guest->id,
            'firstname' => $guest->firstname,
            'lastname' => $guest->lastname,
            'rsvp_status' => $guest->rsvp_status ?? null,
            'app_access' => (bool) ($guest->app_access ?? false),
            'drinks_access' => (bool) ($guest->drinks_access ?? false),
            'food_specials' => $guest->foodSpecials->pluck('name')->all(),
            'photos' => $guest->photos->map(fn (Photo $p) => $this->serializePhoto($p))->all(),
            'drink_logs' => $drinkLogs->map(fn (DrinkLog $log) => [
                'drink_id' => $log->drink_id,
                'amount_liter' => $log->amount_liter,
                'base_points' => $log->base_points,
                'final_points' => $log->final_points,
                'logged_at' => optional($log->created_at)->toIso8601String(),
            ])->all(),
            'invitation_token' => $guest->invitationToken
                ? ['token' => $guest->invitationToken->token, 'created_at' => optional($guest->invitationToken->created_at)->toIso8601String()]
                : null,
            'created_at' => optional($guest->created_at)->toIso8601String(),
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
            'uploaded_by' => $photo->uploaded_by,
            'created_at' => optional($photo->created_at)->toIso8601String(),
        ];
    }
}
