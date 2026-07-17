<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevicePairing;
use App\Models\Event;
use App\Services\EventThemePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementProfileController extends Controller
{
    public function __construct(private readonly EventThemePresenter $themes) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var DevicePairing $session */
        $session = $request->attributes->get('management_pairing');
        /** @var Event $event */
        $event = $request->attributes->get('management_event');
        $devices = $user->devicePairings()
            ->where('event_id', $event->id)
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
                'event_id' => $pairing->event_id,
                'device_label' => $pairing->device_label,
                'paired_at' => $pairing->redeemed_at?->toIso8601String(),
                'last_used_at' => $pairing->accessToken?->last_used_at?->toIso8601String(),
            ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'event' => $this->presentEvent($user, $event),
            'current_device_id' => $session->id,
            'devices' => $devices,
        ]);
    }

    /** Bound-session bootstrap; intentionally exempt from the X-Event-ID header. */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var Event $event */
        $event = $request->attributes->get('management_event');

        return response()->json(['events' => [$this->presentEvent($user, $event)]]);
    }

    /** @return array<string, mixed> */
    private function presentEvent($user, Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date,
            'my_role' => $user->roleOn($event),
            'theme' => $this->themes->present($event),
        ];
    }
}
