<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\CardController;
use App\Http\Controllers\Finance\CategoryController;
use App\Http\Controllers\Finance\CategoryRuleController;
use App\Http\Controllers\Finance\CompanyAnalysisController;
use App\Http\Controllers\Finance\InvestmentController;
use App\Http\Controllers\Finance\InvestmentNewsController;
use App\Http\Controllers\Finance\InvestmentNoteController;
use App\Http\Controllers\Finance\InvestmentPositionController;
use App\Http\Controllers\Finance\MarketController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\TransactionImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('investments', [InvestmentController::class, 'index'])->name('investments.index');
    Route::post('investments/refresh', [InvestmentController::class, 'refresh'])->name('investments.refresh');
    Route::post('investments/refresh-realtime', [InvestmentController::class, 'refreshRealtime'])->name('investments.refresh-realtime');
    Route::get('investments/positions/{isin}', [InvestmentPositionController::class, 'show'])->name('investments.positions.show');
    Route::post('investments/positions/{isin}/notes', [InvestmentNoteController::class, 'store'])->name('investments.notes.store');
    Route::delete('investments/notes/{note}', [InvestmentNoteController::class, 'destroy'])->name('investments.notes.destroy');
    Route::post('investments/positions/{isin}/news/refresh', [InvestmentNewsController::class, 'refresh'])->name('investments.news.refresh');

    Route::get('market', [MarketController::class, 'index'])->name('market.index');

    Route::get('company-analyses', [CompanyAnalysisController::class, 'index'])->name('company-analyses.index');
    Route::get('company-analyses/create', [CompanyAnalysisController::class, 'create'])->name('company-analyses.create');
    Route::post('company-analyses', [CompanyAnalysisController::class, 'store'])->name('company-analyses.store');
    Route::get('company-analyses/{companyAnalysis}', [CompanyAnalysisController::class, 'show'])->name('company-analyses.show');
    Route::patch('company-analyses/{companyAnalysis}', [CompanyAnalysisController::class, 'update'])->name('company-analyses.update');
    Route::post('company-analyses/{companyAnalysis}/refresh-indicators', [CompanyAnalysisController::class, 'refreshIndicators'])->name('company-analyses.refresh-indicators');
    Route::post('company-analyses/{companyAnalysis}/refresh-price-history', [CompanyAnalysisController::class, 'refreshPriceHistory'])->name('company-analyses.refresh-price-history');
    Route::delete('company-analyses/{companyAnalysis}', [CompanyAnalysisController::class, 'destroy'])->name('company-analyses.destroy');

    Route::get('financial-accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('financial-accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('financial-accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('financial-accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('financial-accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('financial-accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::get('cards/{card}/edit', [CardController::class, 'edit'])->name('cards.edit');
    Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
    Route::post('cards', [CardController::class, 'store'])->name('cards.store');
    Route::put('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('cards/{card}/transactions/import', [TransactionImportController::class, 'store'])->name('transactions.import');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::delete('financial-accounts/{account}/transactions', [TransactionController::class, 'destroyAllForAccount'])->name('accounts.transactions.destroy');
    Route::delete('cards/{card}/transactions', [TransactionController::class, 'destroyAllForCard'])->name('cards.transactions.destroy');

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
