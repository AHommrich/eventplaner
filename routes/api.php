<?php

use App\Http\Controllers\Api\EventInfoController;
use App\Http\Controllers\Api\GuestApiController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\QrAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Wird von React Native und anderen API-Clients genutzt.
| Auth via Sanctum Bearer Token (Authorization: Bearer {token})
*/

// QR-Code Login: kein Auth nötig, Token im URL identifiziert den Gast
Route::get('/auth/qr/{token}', [QrAuthController::class, 'login']);

// Logout: löscht den aktuellen Bearer Token serverseitig
// Braucht: Authorization: Bearer {token} im Header — kein Body
Route::delete('/auth/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out.']);
})->middleware('auth:sanctum');

// Fotos: hochladen und abrufen
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos', [PhotoController::class, 'index']);

    // Event-Info (inkl. rsvp_deadline)
    Route::get('/event/info', [EventInfoController::class, 'show']);

    // Gast-Profil + RSVP
    Route::get('/guest/me', [GuestApiController::class, 'me']);
    Route::post('/guest/rsvp/revoke', [GuestApiController::class, 'revoke']);
    Route::post('/guest/rsvp', [GuestApiController::class, 'rsvp']);
    Route::post('/guest/{guestId}/rsvp', [GuestApiController::class, 'rsvpForMember']);
});
