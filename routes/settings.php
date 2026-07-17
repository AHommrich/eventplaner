<?php

use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Settings\DataExportController;
use App\Http\Controllers\Settings\DeviceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    // "My Events" — accessible events + the user's role on each (badges via shared props).
    Route::get('settings/events', function () {
        return Inertia::render('settings/Events');
    })->name('settings.events');

    Route::get('settings/devices', [DeviceController::class, 'index'])->name('settings.devices');
    Route::post('settings/devices/pairings', [DevicePairingController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('settings.devices.pairings');
    Route::delete('settings/devices/{devicePairing}', [DevicePairingController::class, 'destroy'])
        ->name('settings.devices.destroy');

    // GDPR right-of-access (Art. 15) + data portability (Art. 20).
    Route::get('settings/export-data', [DataExportController::class, 'download'])
        ->middleware('throttle:6,1')
        ->name('settings.export-data');
});
