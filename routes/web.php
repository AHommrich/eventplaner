<?php

use App\Models\Guest;
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

//Main Routes
Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');
Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('table', [TableController::class, 'index'])->middleware(['auth', 'verified'])->name('table');
Route::get('invitations', [InvitationController::class, 'index'])->middleware(['auth', 'verified'])->name('invitations');
Route::get('photos', [PhotoController::class, 'index'])->middleware(['auth', 'verified'])->name('photos');
Route::post('photos', [PhotoController::class, 'store'])->middleware(['auth', 'verified'])->name('photos.store');
Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->middleware(['auth', 'verified'])->name('photos.destroy');
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
Route::post('/foodspecials', [FoodSpecialController::class, 'store'])->name('foodspecials.store');

//Guests
Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->middleware(['auth', 'verified'])->name('guests.edit');
Route::put('/guests/{guest}', [GuestController::class, 'update'])->middleware(['auth', 'verified'])->name('guests.update');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
