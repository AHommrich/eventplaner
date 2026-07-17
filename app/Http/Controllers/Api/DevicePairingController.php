<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePairing;
use App\Models\User;
use App\Services\ManagementTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevicePairingController extends Controller
{
    public function __construct(private readonly ManagementTokenService $tokens) {}

    /** Create a short-lived, single-use pairing secret for the current user. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_label' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $plainToken = Str::random(64);
        $expiresAt = now()->addMinutes(config('sanctum.pairing_token_ttl_minutes', 10));

        $pairing = DB::transaction(function () use ($request, $data, $plainToken, $expiresAt) {
            $user = User::query()->lockForUpdate()->find($request->user()->id);
            abort_unless($user instanceof User && $user->hasVerifiedEmail() && $user->isApproved(), 403);

            // The user-row lock serializes concurrent tabs so exactly one
            // pending QR survives as the newest challenge.
            $user->devicePairings()->whereNull('redeemed_at')->delete();

            return $user->devicePairings()->create([
                'token_hash' => hash('sha256', $plainToken),
                'device_label' => $data['device_label'] ?? null,
                'expires_at' => $expiresAt,
            ]);
        });

        return response()->json([
            'pairing_id' => $pairing->id,
            'pairing_token' => $plainToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }

    /** Atomically exchange an unexpired pairing secret for one management token. */
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $tokenHash = hash('sha256', $data['token']);

        $result = DB::transaction(function () use ($tokenHash, $data) {
            $pairing = DevicePairing::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $pairing || $pairing->redeemed_at || $pairing->expires_at->isPast()) {
                return null;
            }

            $user = $pairing->user;
            if (! $user instanceof User || ! $user->hasVerifiedEmail() || ! $user->isApproved()) {
                return ['forbidden' => true];
            }

            $accessToken = $this->tokens->issue($user, $data['device_name'], $pairing);

            return [
                'token' => $accessToken->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ];
        });

        if ($result === null) {
            return response()->json(['message' => 'Pairing token is invalid, expired, or already used.'], 422);
        }

        if (isset($result['forbidden'])) {
            return response()->json(['message' => 'Account is not authorized for management access.'], 403);
        }

        return response()->json($result);
    }

    /** Revoke exactly the Sanctum token minted for this paired device. */
    public function destroy(Request $request, DevicePairing $devicePairing)
    {
        abort_if($devicePairing->user_id !== $request->user()->id, 403);

        DB::transaction(function () use ($devicePairing) {
            $accessToken = $devicePairing->accessToken;
            $accessToken ? $accessToken->delete() : $devicePairing->delete();
        });

        return response()->noContent();
    }
}
