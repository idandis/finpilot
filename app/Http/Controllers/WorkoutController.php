<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workouts\WorkoutStoreRequest;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Workouts\ExerciseCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    /**
     * The board defaults to the current week, but any other week can be
     * requested via ?date= (any date within it) to browse forward/back -
     * grouping by day happens client-side from this flat per-week array.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $weekStart = $this->resolveWeekStart($request->query('date'), $today);
        $weekEnd = $weekStart->copy()->addDays(6);

        $workouts = $request->user()->workouts()
            ->whereDate('workout_date', '>=', $weekStart)
            ->whereDate('workout_date', '<=', $weekEnd)
            ->with(['exercises.exercise', 'exercises.sets'])
            ->get();

        return Inertia::render('Workouts/Index', [
            'weekStart' => $weekStart->toDateString(),
            'today' => $today->toDateString(),
            'workouts' => $workouts->map(fn (Workout $workout) => $this->serializeWorkout($workout)),
            'exercises' => $request->user()->exercises()->orderBy('name')->get(['id', 'name', 'category', 'requires_equipment']),
            'exerciseCategories' => ExerciseCategories::ALL,
        ]);
    }

    public function show(Request $request, Workout $workout): Response
    {
        abort_unless($workout->user_id === $request->user()->id, 403);

        $workout->load(['exercises.exercise', 'exercises.sets']);

        return Inertia::render('Workouts/Show', [
            'workout' => $this->serializeWorkout($workout),
        ]);
    }

    /**
     * Creates the day's workout on first use, or reuses the existing one -
     * either way the submitted exercise rows are appended after whatever
     * exercises are already there, each with its series generated upfront
     * so they can be checked off individually.
     */
    public function store(WorkoutStoreRequest $request): RedirectResponse
    {
        $workout = $request->user()->workouts()
            ->whereDate('workout_date', $request->validated('workout_date'))
            ->first();

        if (! $workout) {
            $workout = $request->user()->workouts()->create([
                'workout_date' => $request->validated('workout_date'),
                'title' => $request->validated('title'),
            ]);
        }

        $nextPosition = 1 + ($workout->exercises()->max('position') ?? -1);

        foreach ($request->validated('exercises') as $row) {
            $workoutExercise = $workout->exercises()->create([
                'exercise_id' => $row['exercise_id'],
                'sets_count' => $row['sets_count'],
                'reps_count' => $row['reps_count'],
                'position' => $nextPosition++,
            ]);

            for ($setNumber = 1; $setNumber <= $row['sets_count']; $setNumber++) {
                $workoutExercise->sets()->create(['set_number' => $setNumber]);
            }
        }

        return back();
    }

    public function removeExercise(Request $request, Workout $workout, WorkoutExercise $workoutExercise): RedirectResponse
    {
        abort_unless($workout->user_id === $request->user()->id, 403);
        abort_unless($workoutExercise->workout_id === $workout->id, 404);

        $workoutExercise->delete();

        return back();
    }

    /**
     * Toggling a set is the core interaction of the execution screen: click
     * a "pallino" to flip that single series between done/not done.
     */
    public function toggleSet(Request $request, WorkoutSet $workoutSet): RedirectResponse
    {
        abort_unless($workoutSet->workoutExercise->workout->user_id === $request->user()->id, 403);

        $workoutSet->update(['completed' => ! $workoutSet->completed]);

        return back();
    }

    public function destroy(Request $request, Workout $workout): RedirectResponse
    {
        abort_unless($workout->user_id === $request->user()->id, 403);

        $workout->delete();

        return back();
    }

    /**
     * Assigns (or changes) the workout's calendar time slot - dragged from
     * the Calendario page, not the Allenamenti board itself (which never
     * shows or needs scheduled_time).
     */
    public function schedule(Request $request, Workout $workout): RedirectResponse
    {
        abort_unless($workout->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'workout_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
        ]);

        $workout->update([
            'workout_date' => $validated['workout_date'],
            'scheduled_time' => Carbon::createFromFormat('H:i', $validated['scheduled_time'])->format('H:i:s'),
        ]);

        return back();
    }

    /**
     * Falls back to the current week on a missing or malformed date - a
     * workout plan is meant to be filled in ahead of time.
     */
    private function resolveWeekStart(?string $requested, Carbon $today): Carbon
    {
        if ($requested === null) {
            return $today->copy()->startOfWeek(Carbon::MONDAY);
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
        } catch (\Throwable) {
            return $today->copy()->startOfWeek(Carbon::MONDAY);
        }

        return $date->startOfWeek(Carbon::MONDAY);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorkout(Workout $workout): array
    {
        return [
            'id' => $workout->id,
            'workout_date' => $workout->workout_date->toDateString(),
            'title' => $workout->title,
            'exercises' => $workout->exercises->map(fn (WorkoutExercise $workoutExercise) => [
                'id' => $workoutExercise->id,
                'exercise_id' => $workoutExercise->exercise_id,
                'exercise_name' => $workoutExercise->exercise->name,
                'category' => $workoutExercise->exercise->category,
                'sets_count' => $workoutExercise->sets_count,
                'reps_count' => $workoutExercise->reps_count,
                'position' => $workoutExercise->position,
                'sets' => $workoutExercise->sets->map(fn (WorkoutSet $set) => [
                    'id' => $set->id,
                    'set_number' => $set->set_number,
                    'completed' => $set->completed,
                ]),
            ]),
        ];
    }
}
