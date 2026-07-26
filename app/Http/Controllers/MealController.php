<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meals\MealMoveRequest;
use App\Http\Requests\Meals\MealStoreRequest;
use App\Models\Meal;
use App\Services\Meals\DishCategories;
use App\Services\Shopping\GroceryCategories;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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

        $meals = $this->weekMeals($request, $weekStart, $weekEnd)
            ->map(fn (Meal $meal) => [
                'id' => $meal->id,
                'title' => $meal->title,
                'description' => $meal->description,
                'meal_date' => $meal->meal_date->toDateString(),
                'meal_type' => $meal->meal_type,
                'category' => $meal->category,
                'position' => $meal->position,
            ]);

        $dishes = $request->user()->dishes()->with('ingredients')->orderBy('name')->get(['id', 'name', 'description', 'category']);

        return Inertia::render('Meals/Index', [
            'weekStart' => $weekStart->toDateString(),
            'today' => $today->toDateString(),
            'meals' => $meals,
            'dishes' => $dishes,
            'dishCategories' => DishCategories::ALL,
            'groceryCategories' => GroceryCategories::ALL,
        ]);
    }

    /**
     * Exports the same week shown on the board as a printable PDF, one
     * section per day split into Pranzo/Cena.
     */
    public function pdf(Request $request): HttpResponse
    {
        $weekStart = $this->resolveWeekStart($request->query('date'), Carbon::today());
        $weekEnd = $weekStart->copy()->addDays(6);

        $meals = $this->weekMeals($request, $weekStart, $weekEnd);

        $days = collect(range(0, 6))->map(fn (int $offset) => [
            'date' => $weekStart->copy()->addDays($offset),
            'lunch' => $meals->filter(fn (Meal $meal) => $meal->meal_date->isSameDay($weekStart->copy()->addDays($offset)) && $meal->meal_type === 'lunch')->values(),
            'dinner' => $meals->filter(fn (Meal $meal) => $meal->meal_date->isSameDay($weekStart->copy()->addDays($offset)) && $meal->meal_type === 'dinner')->values(),
        ]);

        $pdf = Pdf::loadView('meals.pdf', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'dishCategories' => DishCategories::ALL,
        ]);

        return $pdf->download("pasti-settimana-{$weekStart->toDateString()}.pdf");
    }

    /**
     * A dish's own category (a broad "what kind of dish is this" label -
     * carne/pesce/pasta_riso/...) is a different classification system from
     * a shopping list item's grocery-aisle category, and the two key sets
     * only partially overlap. Only map the labels that mean the same thing
     * in both systems; everything else safely falls back to "Altro" instead
     * of silently writing an unrecognized category (which would make the
     * item vanish from the shopping list's category grouping).
     *
     * @var array<string, string>
     */
    private const DISH_TO_GROCERY_CATEGORY = [
        'carne' => 'carne',
        'pesce' => 'pesce',
    ];

    /**
     * Builds a new shopping list from every meal in the requested week: a
     * dish's own ingredients when the meal was dragged in from the library,
     * or the meal's own title as a single item otherwise. Items are
     * deduplicated by name+category so a repeated dish across the week only
     * adds its ingredients once.
     */
    public function generateShoppingList(Request $request): RedirectResponse
    {
        $weekStart = $this->resolveWeekStart($request->query('date'), Carbon::today());
        $weekEnd = $weekStart->copy()->addDays(6);

        $meals = $request->user()->meals()
            ->whereDate('meal_date', '>=', $weekStart)
            ->whereDate('meal_date', '<=', $weekEnd)
            ->with('dish.ingredients')
            ->get();

        $items = collect();

        foreach ($meals as $meal) {
            if ($meal->dish && $meal->dish->ingredients->isNotEmpty()) {
                foreach ($meal->dish->ingredients as $ingredient) {
                    $items->push(['name' => $ingredient->name, 'category' => $ingredient->category]);
                }
            } else {
                $items->push([
                    'name' => $meal->title,
                    'category' => self::DISH_TO_GROCERY_CATEGORY[$meal->category] ?? 'altro',
                ]);
            }
        }

        $deduped = $items->unique(fn (array $item) => mb_strtolower(trim($item['name'])).'|'.$item['category'])->values();

        if ($deduped->isEmpty()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Nessun pasto in questa settimana da cui generare una lista.']);

            return back();
        }

        $list = $request->user()->shoppingLists()->create([
            'name' => 'Spesa settimana del '.$weekStart->format('d/m/Y'),
        ]);

        $positions = [];

        foreach ($deduped as $item) {
            $category = $item['category'];
            $positions[$category] = 1 + ($positions[$category] ?? -1);

            $list->items()->create([
                'name' => $item['name'],
                'category' => $category,
                'position' => $positions[$category],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lista della spesa generata dai pasti della settimana.']);

        return to_route('shopping-lists.show', $list);
    }

    /**
     * @return Collection<int, Meal>
     */
    private function weekMeals(Request $request, Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return $request->user()->meals()
            ->whereDate('meal_date', '>=', $weekStart)
            ->whereDate('meal_date', '<=', $weekEnd)
            ->orderBy('position')
            ->get(['id', 'title', 'description', 'meal_date', 'meal_type', 'category', 'position']);
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
