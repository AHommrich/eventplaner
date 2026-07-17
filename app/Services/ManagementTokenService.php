<?php

namespace App\Services;

use App\Models\DevicePairing;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class ManagementTokenService
{
    /** Issue one expiring management bearer and its revocable session record. */
    public function issue(
        User $user,
        Event $event,
        string $deviceLabel,
        ?DevicePairing $pairing = null,
    ): NewAccessToken {
        return DB::transaction(function () use ($user, $event, $deviceLabel, $pairing) {
            if ($pairing && $pairing->event_id !== $event->id) {
                throw new \InvalidArgumentException('The pairing event does not match the token event.');
            }

            $expiresAt = now()->addDays((int) config('sanctum.management_token_ttl_days', 90));
            $token = $user->createToken(
                $deviceLabel,
                ['management:event:'.$event->id],
                $expiresAt,
            );

            $session = $pairing ?? new DevicePairing([
                'user_id' => $user->id,
                'event_id' => $event->id,
            ]);
            $session->fill([
                'event_id' => $event->id,
                // A redeemed challenge no longer needs to retain even its hash.
                'token_hash' => null,
                'device_label' => $pairing?->device_label ?: $deviceLabel,
                'expires_at' => $expiresAt,
                'redeemed_at' => now(),
                'personal_access_token_id' => $token->accessToken->id,
            ]);
            $session->save();

            return $token;
        });
    }
}
