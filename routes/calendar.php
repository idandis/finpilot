<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('events/{event}/reschedule', [EventController::class, 'reschedule'])->name('events.reschedule');
    Route::patch('events/{event}/resize', [EventController::class, 'resize'])->name('events.resize');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
});
