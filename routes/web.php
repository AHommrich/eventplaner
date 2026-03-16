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
use App\Http\Controllers\EventController;
use App\Http\Controllers\Admin\UserController;

Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');

// Onboarding für eingeloggte User ohne Event
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [EventController::class, 'onboarding'])->name('onboarding');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
});

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

    // User-Verwaltung
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/add-to-event', [UserController::class, 'addToEvent'])->name('admin.users.addToEvent');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
