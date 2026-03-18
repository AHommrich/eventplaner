<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\InvitationToken;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class QrAuthController extends Controller
{
    /**
     * QR-Code Login: Tauscht einen Einladungstoken gegen einen Sanctum Bearer Token.
     *
     * Der Token gehört entweder einer Gruppe (mehrere Gäste)
     * oder einem Einzelgast ohne Gruppe.
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

        $result = $guests->map(function ($guest) use ($isGroup) {
            // Explizit auf tokenable_type + tokenable_id scopen statt über
            // die MorphMany-Relation auf eager-geladenen Objekten zu gehen.
            $hasActiveToken = PersonalAccessToken::where('tokenable_type', Guest::class)
                ->where('tokenable_id', $guest->id)
                ->exists();

            // Solo-Gäste: Token immer erneuern (nur ein möglicher Nutzer)
            // Familien-Gäste: nur neuen Token ausstellen wenn noch keiner aktiv ist
            if (!$isGroup || !$hasActiveToken) {
                PersonalAccessToken::where('tokenable_type', Guest::class)
                    ->where('tokenable_id', $guest->id)
                    ->delete();
                $sanctumToken = $guest->createToken('guest-login', ['role:guest']);
                $plainToken   = $sanctumToken->plainTextToken;
            } else {
                $plainToken = null;
            }

            return [
                'guest_id'  => $guest->id,
                'firstname' => $guest->firstname,
                'lastname'  => $guest->lastname,
                'token'     => $plainToken,
                'is_active' => $hasActiveToken,
            ];
        });

        return response()->json([
            'type'        => $isGroup ? 'family' : 'solo',
            'family_name' => $groupName,
            'guests'      => $result,
        ]);
    }
}
