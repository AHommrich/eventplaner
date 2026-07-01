<?php

use App\Http\Controllers\Api\DrinkLogController;
use App\Http\Controllers\Api\EventInfoController;
use App\Http\Controllers\Api\GuestApiController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PhotoGameController as ApiPhotoGameController;
use App\Http\Controllers\Api\QrAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Used by React Native and other API clients.
| Auth via Sanctum bearer token (Authorization: Bearer {token})
*/

// QR-code login: no auth needed, token in the URL identifies the guest
Route::get('/auth/qr/{token}', [QrAuthController::class, 'login']);
// Family picker: guest selects themselves, token is only now issued
Route::post('/auth/qr/{token}/select', [QrAuthController::class, 'select']);

// Logout: deletes the current bearer token server-side
// Requires: Authorization: Bearer {token} in the header — no body
Route::delete('/auth/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out.']);
})->middleware('auth:sanctum');

// Photos: upload and fetch
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureGuestHasAppAccess::class])->group(function () {
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos', [PhotoController::class, 'index']);

    // Event info (incl. rsvp_deadline)
    Route::get('/event/info', [EventInfoController::class, 'show']);

    // Guest profile + RSVP
    Route::get('/guest/me', [GuestApiController::class, 'me']);
    Route::post('/guest/rsvp/revoke', [GuestApiController::class, 'revoke']);
    Route::post('/guest/rsvp', [GuestApiController::class, 'rsvp']);
    Route::post('/guest/{guestId}/rsvp', [GuestApiController::class, 'rsvpForMember']);

    // Photo game
    Route::get('/game/photo/status', [ApiPhotoGameController::class, 'status']);
    Route::post('/game/photo/assign', [ApiPhotoGameController::class, 'assign']);
    Route::post('/game/photo/submit', [ApiPhotoGameController::class, 'submit']);

    // Drink tracking (drinks_access guard)
    Route::middleware(\App\Http\Middleware\EnsureGuestHasDrinksAccess::class)->group(function () {
        Route::get('/drinks', [DrinkLogController::class, 'index']);
        Route::post('/drinks/log', [DrinkLogController::class, 'log']);
        Route::get('/drinks/stats', [DrinkLogController::class, 'stats']);
    });
});
