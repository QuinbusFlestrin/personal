<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VenueController;
use App\Livewire\Events\EventsIndex;
use App\Livewire\Events\EventSubmitForm;
use App\Livewire\Venues\VenuesIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/events', EventsIndex::class)->name('events.index');
Route::get('/events/submit', EventSubmitForm::class)->name('events.submit');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('/venues', VenuesIndex::class)->name('venues.index');
Route::get('/venues/{venue}', [VenueController::class, 'show'])->name('venues.show');

// Hit by Infomaniak's task scheduler; see App\Http\Controllers\CronController.
Route::get('/cron/run', CronController::class)->name('cron.run');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
