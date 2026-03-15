<?php

use App\Http\Controllers\Api\QrAuthController;
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
