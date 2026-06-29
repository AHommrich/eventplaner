<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * QR-Login für Gäste — Sanctum-Bearer-Token via Einladungs-Token aus dem QR-Code.
 *
 * Solo-Gast:  GET /api/auth/qr/{token} liefert den Token sofort zurück (kein Picker).
 * Familie:    GET liefert die Mitgliederliste OHNE Tokens.
 *             POST /api/auth/qr/{token}/select { guest_id } stellt erst dann einen
 *             Token für den gewählten Gast aus.
 *
 * Tokens werden bewusst NICHT beim ersten Scan für alle Mitglieder vorab erstellt —
 * sonst würden ungenutzte Tokens andere Familienmitglieder blockieren (`is_active`
 * wäre true, obwohl niemand eingeloggt ist). Daher der zweistufige Flow.
 *
 * `is_active`-Prüfung läuft explizit über {@see PersonalAccessToken}, nicht über die
 * `$guest->tokens()`-Relation — auf eager-geladenen Objekten scoped MorphMany dort
 * nicht korrekt.
 */
class QrAuthController extends Controller
{
    /**
     * Schritt 1: QR-Code scannen — gibt Gästeliste zurück, erstellt KEINE Tokens.
     *
     * Für Solo-Gäste wird der Token direkt ausgestellt (kein Picker nötig).
     * Für Familien-Gäste muss danach /auth/qr/{token}/select aufgerufen werden.
     */
    public function login(string $token): JsonResponse
    {
        $invitation = InvitationToken::with(['group.guests', 'guest'])
            ->where('token', $token)
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Ungültiger Einladungslink.'], 404);
        }

        $guests = $invitation->guests();

        if ($guests->isEmpty()) {
            return response()->json(['message' => 'Keine Gäste für diesen Token gefunden.'], 404);
        }

        if ($guests->every(fn($g) => !$g->app_access)) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.'], 403);
        }

        $isGroup   = $invitation->group_id !== null;
        $groupName = $isGroup ? $invitation->group->name : null;

        // Solo-Gast: Token direkt ausstellen (kein Picker, kein Select-Schritt nötig)
        if (!$isGroup) {
            $guest = $guests->first();
            $guest->tokens()->delete();
            $sanctumToken = $guest->createToken('guest-login', ['role:guest']);

            return response()->json([
                'type'   => 'solo',
                'guests' => [[
                    'guest_id'  => $guest->id,
                    'firstname' => $guest->firstname,
                    'lastname'  => $guest->lastname,
                    'token'     => $sanctumToken->plainTextToken,
                    'is_active' => false,
                ]],
            ]);
        }

        // Familien-Gäste: nur Status zurückgeben, KEIN Token erstellen
        $result = $guests->map(fn($guest) => [
            'guest_id'  => $guest->id,
            'firstname' => $guest->firstname,
            'lastname'  => $guest->lastname,
            'token'     => null,
            'is_active' => PersonalAccessToken::where('tokenable_type', Guest::class)
                ->where('tokenable_id', $guest->id)
                ->exists(),
        ]);

        return response()->json([
            'type'        => 'family',
            'family_name' => $groupName,
            'guests'      => $result,
        ]);
    }

    /**
     * Schritt 2 (nur Familie): Gast wählt sich aus — Token wird jetzt erst erstellt.
     *
     * Body: { "guest_id": 42 }
     * Gibt token zurück wenn Gast noch nicht aktiv, sonst Fehler 409.
     */
    public function select(string $token, Request $request): JsonResponse
    {
        $invitation = InvitationToken::with(['group.guests'])
            ->where('token', $token)
            ->whereNotNull('group_id')
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Ungültiger Einladungslink.'], 404);
        }

        $guestId = $request->input('guest_id');
        $guest   = $invitation->group->guests->firstWhere('id', $guestId);

        if (!$guest) {
            return response()->json(['message' => 'Gast gehört nicht zu dieser Gruppe.'], 403);
        }

        if (!$guest->app_access) {
            return response()->json(['message' => 'Der App-Zugang wurde für diesen Gast deaktiviert.'], 403);
        }

        $alreadyActive = PersonalAccessToken::where('tokenable_type', Guest::class)
            ->where('tokenable_id', $guest->id)
            ->exists();

        if ($alreadyActive) {
            return response()->json(['message' => 'Dieser Gast ist bereits eingeloggt.'], 409);
        }

        $sanctumToken = $guest->createToken('guest-login', ['role:guest']);

        return response()->json([
            'guest_id'  => $guest->id,
            'firstname' => $guest->firstname,
            'lastname'  => $guest->lastname,
            'token'     => $sanctumToken->plainTextToken,
        ]);
    }
}
