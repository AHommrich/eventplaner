<?php

use App\Models\Badge;
use App\Models\Family;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'badges' => Badge::orderBy('title', 'asc')->get(['id', 'title']),
        'guests' => Guest::with('badge', 'family', 'drinks')->get(),
        'families' => Family::orderBy('name', 'asc')->get(['id', 'name']),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('table', function (Request $request) {
    $query = Guest::with('badge', 'family', 'drinks');

    // Filter nach Badge-ID
    if ($request->filled('badge_id')) {
        $query->where('badge_id', $request->badge_id);
    }

    return Inertia::render('Table', [
        'guests'  => $query->get(),
        'badges'  => Badge::orderBy('title', 'asc')->get(['id', 'title']),
        'families'=> Family::orderBy('name', 'asc')->get(['id', 'name']),
        'filters' => [
            'badge_id' => $request->badge_id,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('table');



require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
Route::post('/badges', [\App\Http\Controllers\BadgeController::class, 'store'])
    ->name('badges.store');
Route::post('/families', [\App\Http\Controllers\FamilyController::class, 'store'])->name('families.store');



Route::post('/guests', [\App\Http\Controllers\GuestController::class, 'store'])->name('guests.store');
Route::delete('/guests/{guest}', [\App\Http\Controllers\GuestController::class, 'destroy'])
    ->name('guests.destroy');
Route::get('/guests/{guest}/edit', [\App\Http\Controllers\GuestController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('guests.edit');
Route::put('/guests/{guest}', [\App\Http\Controllers\GuestController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('guests.update');
