<?php

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
use App\Http\Controllers\DrinkController;
use App\Http\Controllers\EventAccessController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EventSettingsController;
use App\Http\Controllers\RequestController;

Route::get('/', function () { return Inertia::render('Welcome'); })->name('home');

// Onboarding + Event-Management für eingeloggte User
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [EventController::class, 'onboarding'])->name('onboarding');
    Route::get('/no-event', [EventController::class, 'noEvent'])->name('no-event');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::post('/events/request', [EventController::class, 'requestEvent'])->name('events.request');
    Route::post('/events/switch', [EventController::class, 'switch'])->name('events.switch');
});

// Hauptapp — zugänglich für alle User mit mind. einem Event
Route::middleware(['auth', 'verified', 'has_event'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('guests', [TableController::class, 'index'])->name('guests.index');
    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations');
    Route::post('invitations/generate', [InvitationTokenController::class, 'generate'])->name('invitations.generate');
    Route::get('photos', [PhotoController::class, 'index'])->name('photos');
    Route::post('photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::post('/foodspecials', [FoodSpecialController::class, 'store'])->name('foodspecials.store');

    // Event-Zugang (für Owner und Superadmin)
    Route::get('/event/access', [EventAccessController::class, 'index'])->name('event.access');
    Route::post('/event/access/invite', [EventAccessController::class, 'invite'])->name('event.access.invite');
    Route::delete('/event/access/{user}', [EventAccessController::class, 'remove'])->name('event.access.remove');

    // Drinks
    Route::get('/drinks', [DrinkController::class, 'index'])->name('drinks.index');
    Route::post('/drinks', [DrinkController::class, 'store'])->name('drinks.store');
    Route::post('/drinks/batch', [DrinkController::class, 'batch'])->name('drinks.batch');
    Route::delete('/drinks/{drink}', [DrinkController::class, 'destroy'])->name('drinks.destroy');
    Route::get('/drinks/game', [DrinkController::class, 'game'])->name('drinks.game');
    Route::post('/drinks/game-settings', [DrinkController::class, 'updateGameSettings'])->name('drinks.game.settings');

    // Event-Einstellungen
    Route::get('/event/settings', [EventSettingsController::class, 'show'])->name('event.settings');
    Route::post('/event/settings', [EventSettingsController::class, 'update'])->name('event.settings.update');
    Route::post('/event/settings/cover', [EventSettingsController::class, 'uploadCover'])->name('event.settings.cover');
    Route::delete('/event/settings/cover', [EventSettingsController::class, 'deleteCover'])->name('event.settings.cover.delete');

    // Anfragen-Management
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('/requests/revocations/{guest}/approve', [RequestController::class, 'approveRevocation'])->name('requests.revocations.approve');
    Route::post('/requests/revocations/{guest}/decline', [RequestController::class, 'declineRevocation'])->name('requests.revocations.decline');
    Route::post('/requests/event-requests/{eventRequest}/approve', [RequestController::class, 'approveEventRequest'])->name('requests.event-requests.approve');
    Route::post('/requests/event-requests/{eventRequest}/decline', [RequestController::class, 'declineEventRequest'])->name('requests.event-requests.decline');

    // Guests
    Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
    Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
    Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
    Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
    Route::post('/guests/{guest}/rsvp', [GuestController::class, 'adminRsvp'])->name('guests.admin-rsvp');
    Route::patch('/guests/{guest}/app-access', [GuestController::class, 'updateAppAccess'])->name('guests.app-access');
    Route::patch('/guests/{guest}/drinks-access', [GuestController::class, 'updateDrinksAccess'])->name('guests.drinks-access');
    Route::delete('/guests/{guest}/drink-logs', [GuestController::class, 'resetDrinkLogs'])->name('guests.drink-logs.reset');
});

// Globale User-Verwaltung — nur Superadmin
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/add-to-event', [UserController::class, 'addToEvent'])->name('admin.users.addToEvent');
    Route::delete('/admin/users/{user}/from-event', [UserController::class, 'removeFromEvent'])->name('admin.users.removeFromEvent');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
