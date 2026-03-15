<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvitationToken;
use Illuminate\Http\JsonResponse;

class QrAuthController extends Controller
{
    /**
     * QR-Code Login: Tauscht einen Einladungstoken gegen einen Sanctum Bearer Token.
     *
     * Der Token im QR-Code gehört entweder einer Familie (mehrere Gäste)
     * oder einem Einzelgast ohne Familie. Jeder Gast der Gruppe bekommt
     * einen eigenen Sanctum-Token zurück.
     */
    public function login(string $token): JsonResponse
    {
        $invitation = InvitationToken::with(['family.guests', 'guest'])
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Ungültiger Einladungslink.'], 404);
        }

        $guests = $invitation->guests();

        if ($guests->isEmpty()) {
            return response()->json(['message' => 'Keine Gäste für diesen Token gefunden.'], 404);
        }

        // Für jeden Gast einen Sanctum-Token ausstellen
        $result = $guests->map(function ($guest) {
            // Alte Tokens löschen damit kein Token-Müll entsteht
            $guest->tokens()->delete();

            $sanctumToken = $guest->createToken('guest-login', ['role:guest']);

            return [
                'guest_id'   => $guest->id,
                'firstname'  => $guest->firstname,
                'lastname'   => $guest->lastname,
                'token'      => $sanctumToken->plainTextToken,
            ];
        });

        return response()->json([
            'guests' => $result,
        ]);
    }
}
