<?php

use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::patch('tasks/{task}/schedule', [TaskController::class, 'schedule'])->name('tasks.schedule');
    Route::patch('tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::patch('tasks/{task}/reschedule', [TaskController::class, 'reschedule'])->name('tasks.reschedule');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::post('task-boards', [TaskBoardController::class, 'store'])->name('task-boards.store');
    Route::post('task-boards/{taskBoard}/members', [TaskBoardController::class, 'storeMember'])->name('task-boards.members.store');
    Route::delete('task-boards/{taskBoard}/members/{user}', [TaskBoardController::class, 'destroyMember'])->name('task-boards.members.destroy');
    Route::patch('task-boards/{taskBoard}', [TaskBoardController::class, 'update'])->name('task-boards.update');
    Route::delete('task-boards/{taskBoard}', [TaskBoardController::class, 'destroy'])->name('task-boards.destroy');
});
