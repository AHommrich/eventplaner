<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * QR login for guests — Sanctum bearer token via invitation token from the QR code.
 *
 * Solo guest:  GET /api/auth/qr/{token} returns the token immediately (no picker).
 * Family:      GET returns the member list WITHOUT tokens.
 *              POST /api/auth/qr/{token}/select { guest_id } only then issues a
 *              token for the chosen guest.
 *

 * Tokens are intentionally NOT created up front for all members on the first scan —
 * otherwise unused tokens would block other family members (`is_active`
 * would be true even though nobody is logged in). Hence the two-step flow.
 *
 * Every guest token is created with an explicit `expires_at` (defaults to 90 days,
 * see `config('sanctum.guest_token_ttl_days')`). Data-minimisation: guest access
 * should not linger indefinitely after the event. `sanctum:prune-expired` runs
 * daily via `routes/console.php` and physically removes expired tokens.
 *
 * The `is_active` check runs explicitly via {@see PersonalAccessToken}, not via the
 * `$guest->tokens()` relation — on eager-loaded objects MorphMany does not scope
 * correctly there.
 */
class QrAuthController extends Controller
{
    /**
     * Step 1: scan QR code — returns guest list, creates NO tokens.
     *
     * For solo guests the token is issued directly (no picker needed).
     * For family guests, /auth/qr/{token}/select must be called afterwards.
     */
    public function login(string $token): JsonResponse
    {
        $invitation = InvitationToken::with(['group.guests', 'guest'])
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Ungültiger Einladungslink.'], 404);
        }

        $guests = $invitation->guests();

        if ($guests->isEmpty()) {
            return response()->json(['message' => 'Keine Gäste für diesen Token gefunden.'], 404);
        }

        if ($guests->every(fn ($g) => ! $g->app_access)) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.'], 403);
        }

        $isGroup = $invitation->group_id !== null;
        $groupName = $isGroup ? $invitation->group->name : null;

        // solo guest: issue token directly (no picker, no select step needed)
        if (! $isGroup) {
            $guest = $guests->first();
            $guest->tokens()->delete();
            $sanctumToken = $guest->createToken('guest-login', ['role:guest'], now()->addDays(config('sanctum.guest_token_ttl_days', 90)));

            return response()->json([
                'type' => 'solo',
                'guests' => [[
                    'guest_id' => $guest->id,
                    'firstname' => $guest->firstname,
                    'lastname' => $guest->lastname,
                    'token' => $sanctumToken->plainTextToken,
                    'is_active' => false,
                ]],
            ]);
        }

        // family guests: only return status, do NOT create a token
        $result = $guests->map(fn ($guest) => [
            'guest_id' => $guest->id,
            'firstname' => $guest->firstname,
            'lastname' => $guest->lastname,
            'token' => null,
            'is_active' => $this->hasActiveToken($guest),
        ]);

        return response()->json([
            'type' => 'family',
            'family_name' => $groupName,
            'guests' => $result,
        ]);
    }

    /**
     * Step 2 (family only): guest picks themselves — token is only created now.
     *
     * Body: { "guest_id": 42 }
     * Returns token if guest is not yet active, otherwise error 409.
     */
    public function select(string $token, Request $request): JsonResponse
    {
        $invitation = InvitationToken::with(['group.guests'])
            ->where('token', $token)
            ->whereNotNull('group_id')
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Ungültiger Einladungslink.'], 404);
        }

        $guestId = $request->input('guest_id');
        $guest = $invitation->group->guests->firstWhere('id', $guestId);

        if (! $guest) {
            return response()->json(['message' => 'Gast gehört nicht zu dieser Gruppe.'], 403);
        }

        if (! $guest->app_access) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.'], 403);
        }

        $alreadyActive = $this->hasActiveToken($guest);

        if ($alreadyActive) {
            return response()->json(['message' => 'Dieser Gast ist bereits eingeloggt.'], 409);
        }

        $sanctumToken = $guest->createToken('guest-login', ['role:guest'], now()->addDays(config('sanctum.guest_token_ttl_days', 90)));

        return response()->json([
            'guest_id' => $guest->id,
            'firstname' => $guest->firstname,
            'lastname' => $guest->lastname,
            'token' => $sanctumToken->plainTextToken,
        ]);
    }

    /**
     * A guest counts as active only while at least one un-expired token exists.
     * Expired tokens linger until `sanctum:prune-expired` runs (scheduled daily);
     * treating them as active would falsely block re-login for the whole day.
     */
    private function hasActiveToken(Guest $guest): bool
    {
        return PersonalAccessToken::where('tokenable_type', Guest::class)
            ->where('tokenable_id', $guest->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
