<?php

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\FoodSpecialController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\InvitationTokenController;

Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');

// Admin-only Routes
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('table', [TableController::class, 'index'])->name('table');
    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations');
    Route::post('invitations/generate', [InvitationTokenController::class, 'generate'])->name('invitations.generate');
    Route::get('photos', [PhotoController::class, 'index'])->name('photos');
    Route::post('photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::post('/foodspecials', [FoodSpecialController::class, 'store'])->name('foodspecials.store');

    // Guests
    Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
    Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
    Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
    Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
