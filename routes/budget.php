<?php

use App\Http\Controllers\Budget\AccountTransferController;
use App\Http\Controllers\Budget\BudgetAccountController;
use App\Http\Controllers\Budget\BudgetCategoryController;
use App\Http\Controllers\Budget\BudgetExpenseController;
use App\Http\Controllers\Budget\MonthlyBudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('budget-categories', BudgetCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('budget-categories/order', [BudgetCategoryController::class, 'reorder'])
        ->name('budget-categories.reorder');

    Route::post('budget-categories/{budgetCategory}/subcategories', [BudgetCategoryController::class, 'storeSubcategory'])
        ->name('budget-categories.subcategories.store');
    Route::patch('budget-subcategories/{subcategory}', [BudgetCategoryController::class, 'updateSubcategory'])
        ->name('budget-subcategories.update');
    Route::delete('budget-subcategories/{subcategory}', [BudgetCategoryController::class, 'destroySubcategory'])
        ->name('budget-subcategories.destroy');

    Route::get('monthly-budgets/pdf', [MonthlyBudgetController::class, 'pdf'])
        ->name('monthly-budgets.pdf');

    // Condivisione del budget: come per la pianificazione dei pasti, il
    // "contenitore" è il proprietario stesso.
    Route::post('budget/members', [MonthlyBudgetController::class, 'storeMember'])
        ->name('budget.members.store');
    Route::delete('budget/{owner}/members/{user}', [MonthlyBudgetController::class, 'destroyMember'])
        ->name('budget.members.destroy');

    // Quale budget si apre all'avvio, anche se è di qualcun altro.
    Route::post('budget/default', [MonthlyBudgetController::class, 'setDefaultBudget'])
        ->name('budget.default');

    Route::resource('monthly-budgets', MonthlyBudgetController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::resource('budget-expenses', BudgetExpenseController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // I conti e le carte da cui passano i movimenti.
    Route::resource('budget-accounts', BudgetAccountController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('budget-accounts/order', [BudgetAccountController::class, 'reorder'])
        ->name('budget-accounts.reorder');

    // Gli spostamenti di denaro da un conto all'altro.
    Route::resource('account-transfers', AccountTransferController::class)
        ->only(['store', 'update', 'destroy']);
});
