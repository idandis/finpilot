<?php

use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('meals', [MealController::class, 'index'])->name('meals.index');
    Route::get('meals/pdf', [MealController::class, 'pdf'])->name('meals.pdf');
    Route::post('meals', [MealController::class, 'store'])->name('meals.store');
    Route::patch('meals/{meal}/move', [MealController::class, 'move'])->name('meals.move');
    Route::delete('meals/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');
});
