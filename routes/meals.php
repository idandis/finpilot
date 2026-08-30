<?php

use App\Http\Controllers\MealController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('meals', [MealController::class, 'index'])->name('meals.index');
    Route::get('meals/pdf', [MealController::class, 'pdf'])->name('meals.pdf');
    Route::post('meals/generate-shopping-list', [MealController::class, 'generateShoppingList'])->name('meals.generate-shopping-list');
    Route::post('meals', [MealController::class, 'store'])->name('meals.store');
    Route::patch('meals/{meal}', [MealController::class, 'update'])->name('meals.update');
    Route::patch('meals/{meal}/move', [MealController::class, 'move'])->name('meals.move');
    Route::patch('meals/{meal}/assign', [MealController::class, 'assign'])->name('meals.assign');
    Route::delete('meals/{meal}', [MealController::class, 'destroy'])->name('meals.destroy');

    Route::post('meal-plan/members', [MealController::class, 'storeMember'])->name('meal-plan.members.store');
    Route::delete('meal-plan/{owner}/members/{user}', [MealController::class, 'destroyMember'])->name('meal-plan.members.destroy');
});
