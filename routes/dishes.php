<?php

use App\Http\Controllers\DishController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::patch('dishes/{dish}', [DishController::class, 'update'])->name('dishes.update');
    Route::delete('dishes/{dish}', [DishController::class, 'destroy'])->name('dishes.destroy');
});
