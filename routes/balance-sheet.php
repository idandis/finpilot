<?php

use App\Http\Controllers\BalanceSheet\AssetController;
use App\Http\Controllers\BalanceSheet\EntryController;
use App\Http\Controllers\BalanceSheet\LedgerController;
use App\Http\Controllers\BalanceSheet\MonthCloseController;
use App\Http\Controllers\BalanceSheet\OverviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('balance-sheet', [OverviewController::class, 'index'])->name('balance-sheet.index');
    Route::get('balance-sheet/ledger', [LedgerController::class, 'index'])->name('balance-sheet.ledger');
    Route::patch('balance-sheet/profile', [OverviewController::class, 'updateProfile'])->name('balance-sheet.profile.update');
    Route::post('balance-sheet/close-month', [MonthCloseController::class, 'store'])->name('balance-sheet.close-month');
    Route::delete('balance-sheet/close-month/{closure}', [MonthCloseController::class, 'destroy'])->name('balance-sheet.close-month.destroy');

    Route::get('balance-sheet/assets', [AssetController::class, 'index'])->name('balance-sheet.assets.index');
    Route::get('balance-sheet/assets/create', [AssetController::class, 'create'])->name('balance-sheet.assets.create');
    Route::post('balance-sheet/assets', [AssetController::class, 'store'])->name('balance-sheet.assets.store');
    Route::get('balance-sheet/assets/{asset}/edit', [AssetController::class, 'edit'])->name('balance-sheet.assets.edit');
    Route::put('balance-sheet/assets/{asset}', [AssetController::class, 'update'])->name('balance-sheet.assets.update');
    Route::delete('balance-sheet/assets/{asset}', [AssetController::class, 'destroy'])->name('balance-sheet.assets.destroy');

    Route::get('balance-sheet/{type}', [EntryController::class, 'index'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.index');
    Route::get('balance-sheet/{type}/create', [EntryController::class, 'create'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.create');
    Route::post('balance-sheet/{type}', [EntryController::class, 'store'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.store');
    Route::get('balance-sheet/{type}/{entry}/edit', [EntryController::class, 'edit'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.edit');
    Route::put('balance-sheet/{type}/{entry}', [EntryController::class, 'update'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.update');
    Route::delete('balance-sheet/{type}/{entry}', [EntryController::class, 'destroy'])
        ->whereIn('type', EntryController::TYPES)
        ->name('balance-sheet.entries.destroy');
});
