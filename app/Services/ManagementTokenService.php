<?php

namespace App\Services;

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class ManagementTokenService
{
    /** Issue one expiring management bearer and its revocable session record. */
    public function issue(User $user, string $deviceLabel, ?DevicePairing $pairing = null): NewAccessToken
    {
        return DB::transaction(function () use ($user, $deviceLabel, $pairing) {
            $expiresAt = now()->addDays((int) config('sanctum.management_token_ttl_days', 90));
            $token = $user->createToken($deviceLabel, ['management:*'], $expiresAt);

            $session = $pairing ?? new DevicePairing(['user_id' => $user->id]);
            $session->fill([
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
