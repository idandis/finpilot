<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\CardController;
use App\Http\Controllers\Finance\CategoryController;
use App\Http\Controllers\Finance\CategoryRuleController;
use App\Http\Controllers\Finance\InvestmentController;
use App\Http\Controllers\Finance\OverviewController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\TransactionImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('overview', [OverviewController::class, 'index'])->name('overview.index');
    Route::get('investments', [InvestmentController::class, 'index'])->name('investments.index');

    Route::get('financial-accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('financial-accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('financial-accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('financial-accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('financial-accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('financial-accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
    Route::post('financial-accounts/{account}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::put('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('cards/{card}/transactions/import', [TransactionImportController::class, 'store'])->name('transactions.import');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::delete('financial-accounts/{account}/transactions', [TransactionController::class, 'destroyAllForAccount'])->name('accounts.transactions.destroy');

    Route::get('category-rules', [CategoryRuleController::class, 'index'])->name('category-rules.index');
    Route::patch('category-rules/{rule}', [CategoryRuleController::class, 'update'])->name('category-rules.update');
    Route::delete('category-rules/{rule}', [CategoryRuleController::class, 'destroy'])->name('category-rules.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::patch('budgets/{category}', [BudgetController::class, 'update'])->name('budgets.update');
});
