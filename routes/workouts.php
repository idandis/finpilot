<?php

use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workouts', [WorkoutController::class, 'index'])->name('workouts.index');
    Route::get('workouts/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');
    Route::post('workouts', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::delete('workouts/{workout}/exercises/{workoutExercise}', [WorkoutController::class, 'removeExercise'])->name('workouts.exercises.destroy');
    Route::patch('workout-sets/{workoutSet}/toggle', [WorkoutController::class, 'toggleSet'])->name('workout-sets.toggle');
    Route::delete('workouts/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
});
