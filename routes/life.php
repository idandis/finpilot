<?php

use App\Http\Controllers\LifeController;
use App\Http\Controllers\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('life', [LifeController::class, 'index'])->name('life.index');
    Route::get('life/{year}/{week}', [LifeController::class, 'week'])
        ->whereNumber('year')
        ->whereNumber('week')
        ->name('life.week');
    Route::post('memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::patch('memories/{memory}', [MemoryController::class, 'update'])->name('memories.update');
    Route::delete('memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');
});
