<?php

use App\Http\Controllers\AiChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('ai-chat', [AiChatController::class, 'index'])->name('ai-chat.index');
    Route::post('ai-chat/messages', [AiChatController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('ai-chat.store');
});
