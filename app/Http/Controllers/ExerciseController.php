<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workouts\ExerciseStoreRequest;
use App\Http\Requests\Workouts\ExerciseUpdateRequest;
use App\Models\Exercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function store(ExerciseStoreRequest $request): RedirectResponse
    {
        $request->user()->exercises()->create($request->validated());

        return back();
    }

    public function update(ExerciseUpdateRequest $request, Exercise $exercise): RedirectResponse
    {
        $exercise->update($request->validated());

        return back();
    }

    public function destroy(Request $request, Exercise $exercise): RedirectResponse
    {
        abort_unless($exercise->user_id === $request->user()->id, 403);

        $exercise->delete();

        return back();
    }
}
