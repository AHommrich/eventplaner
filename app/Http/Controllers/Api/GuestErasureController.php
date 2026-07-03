<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GDPR Art. 17 (right to erasure) endpoint for the in-app guest.
 *
 * Flow:
 *   POST /api/guest/erasure          → schedule delete in `retention.guest_erasure_grace_days`
 *                                      days, revoke current Sanctum tokens, return a one-time
 *                                      recovery token (in plain text — hashed in the DB).
 *   POST /api/guest/erasure/revoke   → present the recovery token to abort the request within
 *                                      the grace window.
 *
 * The recovery token is returned in the request response only. Guests have no
 * email in this system (see docs/legal/) so we can't fall back to an email
 * link — the client must persist the token locally and show it to the user
 * for safekeeping. If they lose it, the only path back is the QR code +
 * event owner.
 */
class GuestErasureController extends Controller
{
    /**
     * POST /api/guest/erasure
     */
    public function request(Request $request): JsonResponse
    {
        /** @var Guest $guest */
        $guest = $request->user();

        if ($guest->scheduled_erasure_at) {
            return response()->json([
                'message' => 'Ein Löschantrag für diesen Gast läuft bereits.',
                'scheduled_erasure_at' => $guest->scheduled_erasure_at->toIso8601String(),
            ], 409);
        }

        $graceDays = (int) config('retention.guest_erasure_grace_days');
        $scheduledAt = now()->addDays($graceDays);

        $plainToken = Str::random(48);

        $guest->forceFill([
            'erasure_requested_at' => now(),
            'scheduled_erasure_at' => $scheduledAt,
            'erasure_recovery_token' => hash('sha256', $plainToken),
        ])->save();

        // Kill the current Sanctum tokens — from now on the guest is logged out.
        $guest->tokens()->delete();

        Log::info('guest.erasure.requested', [
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
            'scheduled_erasure_at' => $scheduledAt->toIso8601String(),
        ]);

        return response()->json([
            'scheduled_erasure_at' => $scheduledAt->toIso8601String(),
            'can_revoke_until' => $scheduledAt->toIso8601String(),
            'recovery_token' => $plainToken,
            'recovery_delivery' => 'response_only',
            'recovery_note' => 'Bewahre diesen Token sicher auf. Er ist der einzige Weg, den Löschantrag ohne erneuten QR-Login zurückzunehmen.',
        ], 201);
    }

    /**
     * POST /api/guest/erasure/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recovery_token' => 'required|string|max:128',
        ]);

        $hashed = hash('sha256', $data['recovery_token']);

        $guest = Guest::query()
            ->where('erasure_recovery_token', $hashed)
            ->first();

        if (! $guest || ! $guest->scheduled_erasure_at) {
            return response()->json(['message' => 'Ungültiger Widerrufs-Token.'], 403);
        }

        if (now()->greaterThan($guest->scheduled_erasure_at)) {
            return response()->json([
                'message' => 'Das Widerrufsfenster ist bereits abgelaufen.',
            ], 410);
        }

        $guest->forceFill([
            'erasure_requested_at' => null,
            'scheduled_erasure_at' => null,
            'erasure_recovery_token' => null,
        ])->save();

        Log::info('guest.erasure.revoked', [
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
        ]);

        return response()->json(['success' => true]);
    }
}
