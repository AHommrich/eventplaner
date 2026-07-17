<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ManagementPushTokenController extends Controller
{
    /** Register, refresh, or opt out an organizer device without an event context. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expo_token' => ['required_unless:enabled,false', 'string', 'max:255', 'regex:/^(Exponent|Expo)PushToken\[[A-Za-z0-9_-]+\]$/'],
            'platform' => ['required_unless:enabled,false', 'in:ios,android'],
            'enabled' => ['sometimes', 'boolean'],
        ]);
        $accessToken = $request->user()->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 403);

        if (($data['enabled'] ?? true) === false) {
            PushToken::query()
                ->where('personal_access_token_id', $accessToken->id)
                ->delete();

            return response()->json(['enabled' => false]);
        }

        // One PAT represents one installation session. Upserting by PAT
        // replaces rotated Expo tokens; deleting a conflicting installation
        // token first safely moves a physical app install between accounts.
        $token = DB::transaction(function () use ($request, $accessToken, $data) {
            // Serialize focus refresh and Expo's rotation callback for this PAT.
            PersonalAccessToken::query()->whereKey($accessToken->id)->lockForUpdate()->firstOrFail();
            PushToken::query()
                ->where('expo_token', $data['expo_token'])
                ->where('personal_access_token_id', '!=', $accessToken->id)
                ->delete();

            return PushToken::query()->updateOrCreate(
                ['personal_access_token_id' => $accessToken->id],
                [
                    'user_id' => $request->user()->id,
                    'expo_token' => $data['expo_token'],
                    'platform' => $data['platform'],
                    'last_used_at' => now(),
                ],
            );
        });

        return response()->json([
            'id' => $token->id,
            'platform' => $token->platform,
            'enabled' => true,
            'last_used_at' => $token->last_used_at->toIso8601String(),
        ]);
    }
}
