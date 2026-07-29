<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/ai-chat.php';
require __DIR__.'/finance.php';
require __DIR__.'/passwords.php';
require __DIR__.'/tasks.php';
require __DIR__.'/shopping-lists.php';
require __DIR__.'/meals.php';
require __DIR__.'/dishes.php';
require __DIR__.'/workouts.php';
require __DIR__.'/exercises.php';
require __DIR__.'/life.php';
