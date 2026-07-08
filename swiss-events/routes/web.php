<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Hit by Infomaniak's task scheduler; see App\Http\Controllers\CronController.
Route::get('/cron/run', CronController::class)->name('cron.run');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
