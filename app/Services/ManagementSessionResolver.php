<?php

namespace App\Services;

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/** Resolve the one event-bound device session represented by a management PAT. */
class ManagementSessionResolver
{
    public function resolve(Request $request): ?DevicePairing
    {
        $user = $request->user();
        $accessToken = $user?->currentAccessToken();

        if (! $user instanceof User
            || ! $accessToken instanceof PersonalAccessToken
            || ! $user->hasVerifiedEmail()
            || ! $user->isApproved()) {
            return null;
        }

        $pairing = DevicePairing::query()
            ->with('event')
            ->where('user_id', $user->id)
            ->where('personal_access_token_id', $accessToken->id)
            ->whereNotNull('redeemed_at')
            ->first();

        $event = $pairing?->event;
        if (! $pairing || ! $event
            || ! $accessToken->can('management:event:'.$event->id)
            || $user->roleOn($event) === null) {
            return null;
        }

        return $pairing;
    }
}
