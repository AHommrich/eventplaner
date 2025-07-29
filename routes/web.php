<?php

use App\Models\Badge;
use App\Models\Family;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TableController;

//Main Routes
Route::get('/', function () {return Inertia::render('Welcome');})->name('home');
Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('table', [TableController::class, 'index'])->middleware(['auth', 'verified'])->name('table');
Route::post('/badges', [BadgeController::class, 'store'])->name('badges.store');
Route::post('/families', [FamilyController::class, 'store'])->name('families.store');

//Guests
Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->middleware(['auth', 'verified'])->name('guests.edit');
Route::put('/guests/{guest}', [GuestController::class, 'update'])->middleware(['auth', 'verified'])->name('guests.update');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
