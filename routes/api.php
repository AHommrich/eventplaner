<?php

use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Api\DrinkLogController;
use App\Http\Controllers\Api\EventInfoController;
use App\Http\Controllers\Api\GuestApiController;
use App\Http\Controllers\Api\GuestContentHideController;
use App\Http\Controllers\Api\GuestDataExportController;
use App\Http\Controllers\Api\GuestErasureController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\ManagementPhotoController;
use App\Http\Controllers\Api\ManagementProfileController;
use App\Http\Controllers\Api\ManagementPushTokenController;
use App\Http\Controllers\Api\ManagementScheduleController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PhotoGameController as ApiPhotoGameController;
use App\Http\Controllers\Api\PhotoReportController;
use App\Http\Controllers\Api\QrAuthController;
use App\Http\Controllers\NoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Used by React Native and other API clients.
| Auth via Sanctum bearer token (Authorization: Bearer {token})
*/

// Privacy policy: public, unauthenticated — the app renders it natively so
// users can read it before they consent. Rate-limited to shrug off abuse.
Route::get('/legal/privacy', [LegalController::class, 'privacy'])
    ->middleware('throttle:30,1');
Route::get('/legal/imprint', [LegalController::class, 'imprint'])
    ->middleware('throttle:30,1');

// QR-code login: no auth needed, token in the URL identifies the guest
Route::get('/auth/qr/{token}', [QrAuthController::class, 'login']);
// Family picker: guest selects themselves, token is only now issued
Route::post('/auth/qr/{token}/select', [QrAuthController::class, 'select']);

// Higher per-IP limit than a typical auth route: several organizer devices are
// paired from the same venue WLAN (same public IP) in quick succession. The
// 64-char one-time secret is the real protection; this only blunts abuse.
Route::post('/auth/pair', [DevicePairingController::class, 'redeem'])
    ->middleware('throttle:20,1');

// Logout: deletes the current bearer token server-side
// Requires: Authorization: Bearer {token} in the header — no body
Route::delete('/auth/logout', function (Request $request) {
    $accessToken = $request->user()->currentAccessToken();
    abort_unless($accessToken instanceof PersonalAccessToken, 403);
    $accessToken->delete();

    return response()->json(['message' => 'Logged out.']);
})->middleware('auth:sanctum');

Route::prefix('management')->middleware(['auth:sanctum', 'management_user'])->group(function () {
    Route::get('/me', [ManagementProfileController::class, 'show']);
    Route::get('/me/events', [ManagementProfileController::class, 'events']);
    Route::post('/push/register', [ManagementPushTokenController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::middleware('management_event:manage')->group(function () {
        Route::get('/notes', [NoteController::class, 'index']);
        Route::post('/notes', [NoteController::class, 'store']);
        Route::patch('/notes/{note}', [NoteController::class, 'update']);
        Route::delete('/notes/{note}', [NoteController::class, 'destroy']);

        Route::get('/photos', [ManagementPhotoController::class, 'index']);
        Route::post('/photos', [ManagementPhotoController::class, 'store']);
        Route::delete('/photos', [ManagementPhotoController::class, 'destroyBatch']);
        Route::delete('/photos/{photo}', [ManagementPhotoController::class, 'destroy']);

        Route::get('/schedule', [ManagementScheduleController::class, 'index']);
    });
});

// GDPR Art. 17 revocation: unauthenticated — the erasure request revoked
// the guest's Sanctum token, so we authenticate them by the one-time recovery
// token instead. Rate-limited to make brute-forcing the 48-char token even
// harder than it already is.
Route::post('/guest/erasure/revoke', [GuestErasureController::class, 'revoke'])
    ->middleware('throttle:6,1');

// Photos: upload and fetch
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureGuestHasAppAccess::class])->group(function () {
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos', [PhotoController::class, 'index']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);

    // Moderation (App Store Guideline 1.2): report a photo, hide/unhide another
    // guest's uploads, list hidden guests. Report is rate-limited so a bad
    // actor cannot spam an owner's inbox.
    Route::post('/photos/{photo}/report', [PhotoReportController::class, 'store'])
        ->middleware('throttle:10,60');
    Route::post('/guests/{guest}/hide-content', [GuestContentHideController::class, 'store']);
    Route::delete('/guests/{guest}/hide-content', [GuestContentHideController::class, 'destroy']);
    Route::get('/guests/hidden-content', [GuestContentHideController::class, 'index']);

    // Event info (incl. rsvp_deadline)
    Route::get('/event/info', [EventInfoController::class, 'show']);

    // Guest profile + RSVP
    Route::get('/guest/me', [GuestApiController::class, 'me']);
    Route::post('/guest/rsvp/revoke', [GuestApiController::class, 'revoke']);
    Route::post('/guest/rsvp', [GuestApiController::class, 'rsvp']);
    Route::post('/guest/{guestId}/rsvp', [GuestApiController::class, 'rsvpForMember']);

    // GDPR Art. 15 (access) + Art. 17 (erasure)
    Route::middleware('throttle:6,1')->group(function () {
        Route::get('/guest/export', [GuestDataExportController::class, 'export']);
        Route::post('/guest/erasure', [GuestErasureController::class, 'request']);
    });

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
