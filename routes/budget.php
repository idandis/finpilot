<?php

use App\Http\Controllers\Budget\BudgetCategoryController;
use App\Http\Controllers\Budget\BudgetExpenseController;
use App\Http\Controllers\Budget\MonthlyBudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('budget-categories', BudgetCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('budget-categories/{budgetCategory}/subcategories', [BudgetCategoryController::class, 'storeSubcategory'])
        ->name('budget-categories.subcategories.store');
    Route::patch('budget-subcategories/{subcategory}', [BudgetCategoryController::class, 'updateSubcategory'])
        ->name('budget-subcategories.update');
    Route::delete('budget-subcategories/{subcategory}', [BudgetCategoryController::class, 'destroySubcategory'])
        ->name('budget-subcategories.destroy');

    Route::resource('monthly-budgets', MonthlyBudgetController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::resource('budget-expenses', BudgetExpenseController::class)
        ->only(['index', 'store', 'destroy']);
});
