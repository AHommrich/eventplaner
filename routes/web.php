<?php

use App\Models\Badge;
use App\Models\Family;
use App\Models\Guest;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'badges' => Badge::all(['id', 'title']),
        'guests' => Guest::with('badge', 'family', 'drinks')->get(),
        'families' => Family::all(['id', 'name']),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
Route::post('/badges', [\App\Http\Controllers\BadgeController::class, 'store'])
    ->name('badges.store');
Route::post('/guests', [\App\Http\Controllers\GuestController::class, 'store'])->name('guests.store');
Route::delete('/guests/{guest}', [\App\Http\Controllers\GuestController::class, 'destroy'])
    ->name('guests.destroy');
Route::post('/families', [\App\Http\Controllers\FamilyController::class, 'store'])->name('families.store');
