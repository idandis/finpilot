<?php

use App\Http\Controllers\PasswordEntryController;
use App\Http\Controllers\PasswordGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('passwords', [PasswordGroupController::class, 'index'])->name('passwords.index');

    Route::post('password-groups', [PasswordGroupController::class, 'store'])->name('password-groups.store');
    Route::patch('password-groups/{passwordGroup}', [PasswordGroupController::class, 'update'])->name('password-groups.update');
    Route::delete('password-groups/{passwordGroup}', [PasswordGroupController::class, 'destroy'])->name('password-groups.destroy');

    Route::post('password-groups/{passwordGroup}/entries', [PasswordEntryController::class, 'store'])->name('password-entries.store');
    Route::patch('password-entries/{passwordEntry}', [PasswordEntryController::class, 'update'])->name('password-entries.update');
    Route::delete('password-entries/{passwordEntry}', [PasswordEntryController::class, 'destroy'])->name('password-entries.destroy');
    Route::get('password-entries/{passwordEntry}/reveal', [PasswordEntryController::class, 'reveal'])->name('password-entries.reveal');
});
