<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $devices = $user->devicePairings()
            ->whereNotNull('redeemed_at')
            ->whereNotNull('personal_access_token_id')
            ->whereHas('accessToken', fn ($query) => $query->where(
                fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now())
            ))
            ->with('accessToken')
            ->latest('redeemed_at')
            ->get()
            ->map(fn ($pairing) => [
                'id' => $pairing->id,
                'device_label' => $pairing->device_label,
                'paired_at' => $pairing->redeemed_at?->toIso8601String(),
                'last_used_at' => $pairing->accessToken?->last_used_at?->toIso8601String(),
            ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'devices' => $devices,
        ]);
    }

    /** Bootstrap list; intentionally exempt from the X-Event-ID contract. */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = $user->accessibleEvents()
            ->orderBy('date')
            ->get(['id', 'name', 'date', 'user_id'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date,
                'my_role' => $user->roleOn($event),
            ]);

        return response()->json(['events' => $events]);
    }
}
