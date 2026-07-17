<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $devices = $request->user()->devicePairings()
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

        return Inertia::render('settings/Devices', ['devices' => $devices]);
    }
}
