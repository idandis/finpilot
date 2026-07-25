<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meals\MealMoveRequest;
use App\Http\Requests\Meals\MealStoreRequest;
use App\Models\Meal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MealController extends Controller
{
    /**
     * The board defaults to the current week, but any other week can be
     * requested via ?date= (any date within it) to browse forward/back -
     * grouping by day and lunch/dinner happens client-side from this flat
     * per-week array.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $weekStart = $this->resolveWeekStart($request->query('date'), $today);
        $weekEnd = $weekStart->copy()->addDays(6);

        $meals = $request->user()->meals()
            ->whereBetween('meal_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('position')
            ->get(['id', 'title', 'description', 'meal_date', 'meal_type', 'position'])
            ->map(fn (Meal $meal) => [
                'id' => $meal->id,
                'title' => $meal->title,
                'description' => $meal->description,
                'meal_date' => $meal->meal_date->toDateString(),
                'meal_type' => $meal->meal_type,
                'position' => $meal->position,
            ]);

        return Inertia::render('Meals/Index', [
            'weekStart' => $weekStart->toDateString(),
            'today' => $today->toDateString(),
            'meals' => $meals,
        ]);
    }

    /**
     * Falls back to the current week on a missing or malformed date -
     * unlike the daily task board, there's no future restriction here: a
     * meal plan is meant to be filled in ahead of time.
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
     * A new meal is always appended to the end of its chosen day/slot.
     */
    public function store(MealStoreRequest $request): RedirectResponse
    {
        $nextPosition = 1 + ($request->user()->meals()
            ->whereDate('meal_date', $request->validated('meal_date'))
            ->where('meal_type', $request->validated('meal_type'))
            ->max('position') ?? -1);

        $request->user()->meals()->create([
            ...$request->validated(),
            'position' => $nextPosition,
        ]);

        return back();
    }

    /**
     * Drag-and-drop between lunch/dinner and/or between days: the meal is
     * always appended to the end of the target day/slot (no fine-grained
     * reordering within one).
     */
    public function move(MealMoveRequest $request, Meal $meal): RedirectResponse
    {
        $mealDate = $request->validated('meal_date');
        $mealType = $request->validated('meal_type');

        $nextPosition = 1 + (Meal::query()
            ->where('user_id', $meal->user_id)
            ->whereDate('meal_date', $mealDate)
            ->where('meal_type', $mealType)
            ->max('position') ?? -1);

        $meal->update(['meal_date' => $mealDate, 'meal_type' => $mealType, 'position' => $nextPosition]);

        return back();
    }

    public function destroy(Request $request, Meal $meal): RedirectResponse
    {
        abort_unless($meal->user_id === $request->user()->id, 403);

        $meal->delete();

        return back();
    }
}
