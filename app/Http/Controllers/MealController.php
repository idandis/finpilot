<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meals\MealAssignRequest;
use App\Http\Requests\Meals\MealMoveRequest;
use App\Http\Requests\Meals\MealPlanMemberStoreRequest;
use App\Http\Requests\Meals\MealStoreRequest;
use App\Http\Requests\Meals\MealUpdateRequest;
use App\Models\Meal;
use App\Models\User;
use App\Services\Meals\DishCategories;
use App\Services\Sharing\SharedResource;
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
     *
     * It also defaults to the user's own meal plan; ?plan=<user id> switches
     * to one shared with them (see resolvePlanOwner()). A shared plan is the
     * very same week seen by everyone on it, each meal optionally on
     * whoever is cooking it.
     *
     * The dish library stays personal either way: dragging one of your own
     * dishes onto somebody else's plan is the normal way to plan together.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();
        $weekStart = $this->resolveWeekStart($request->query('date'), $today);
        $weekEnd = $weekStart->copy()->addDays(6);
        $owner = $this->resolvePlanOwner($user, $request->query('plan'));

        $meals = $this->weekMeals($owner, $weekStart, $weekEnd)
            ->map(fn (Meal $meal) => [
                'id' => $meal->id,
                'title' => $meal->title,
                'description' => $meal->description,
                'meal_date' => $meal->meal_date->toDateString(),
                'meal_type' => $meal->meal_type,
                'category' => $meal->category,
                'position' => $meal->position,
                'assignee' => $meal->assignee?->only(['id', 'name']),
            ]);

        $dishes = $user->dishes()->with('ingredients')->orderBy('name')->get(['id', 'name', 'description', 'category']);

        return Inertia::render('Meals/Index', [
            'weekStart' => $weekStart->toDateString(),
            'today' => $today->toDateString(),
            'plans' => $this->accessiblePlans($user),
            'plan' => $this->serializePlan($owner, $user),
            'meals' => $meals,
            'dishes' => $dishes,
            'dishCategories' => DishCategories::ALL,
            'groceryCategories' => GroceryCategories::ALL,
        ]);
    }

    /**
     * The user's own plan by default - also where an unknown id or a plan
     * they were never invited to (or have just been removed from) lands,
     * the same forgiving treatment as resolveWeekStart().
     */
    private function resolvePlanOwner(User $user, mixed $requested): User
    {
        if (! is_numeric($requested)) {
            return $user;
        }

        $owner = User::query()->find((int) $requested);

        return $owner?->mealPlanIsAccessibleBy($user) ? $owner : $user;
    }

    /**
     * The plan switcher: the user's own plan first, then the ones shared
     * with them, each identified by its owner.
     *
     * @return Collection<int, array{id: int, name: string, is_shared: bool}>
     */
    private function accessiblePlans(User $user): Collection
    {
        return collect([['id' => $user->id, 'name' => 'I miei pasti', 'is_shared' => false]])
            ->concat($user->sharedMealPlans()->orderBy('name')->get()
                ->map(fn (User $owner) => ['id' => $owner->id, 'name' => $owner->name, 'is_shared' => true]));
    }

    /**
     * The plan being shown, with everyone on it - the list behind both the
     * sharing panel and the cook picker.
     *
     * @return array<string, mixed>
     */
    private function serializePlan(User $owner, User $user): array
    {
        $owner->load('mealPlanMembers:id,name,email');

        return [
            'id' => $owner->id,
            'name' => $owner->id === $user->id ? 'I miei pasti' : $owner->name,
            'is_owner' => $owner->id === $user->id,
            'people' => $owner->mealPlanPeople()->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'is_owner' => $person->id === $owner->id,
            ])->all(),
        ];
    }

    /**
     * Exports the same week shown on the board as a printable PDF, one
     * section per day split into Pranzo/Cena.
     */
    public function pdf(Request $request): HttpResponse
    {
        $weekStart = $this->resolveWeekStart($request->query('date'), Carbon::today());
        $weekEnd = $weekStart->copy()->addDays(6);
        $owner = $this->resolvePlanOwner($request->user(), $request->query('plan'));

        $meals = $this->weekMeals($owner, $weekStart, $weekEnd);

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

        $owner = $this->resolvePlanOwner($request->user(), $request->query('plan'));

        $meals = $owner->meals()
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
    private function weekMeals(User $owner, Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return $owner->meals()
            ->whereDate('meal_date', '>=', $weekStart)
            ->whereDate('meal_date', '<=', $weekEnd)
            ->with('assignee:id,name')
            ->orderBy('position')
            ->get(['id', 'user_id', 'assigned_to_user_id', 'title', 'description', 'meal_date', 'meal_type', 'category', 'position']);
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
     * A new meal is always appended to the end of its chosen day/slot, on
     * the plan it was added to (the user's own unless plan_user_id points
     * at one shared with them).
     */
    public function store(MealStoreRequest $request): RedirectResponse
    {
        $owner = $request->planOwner();

        $nextPosition = 1 + ($owner->meals()
            ->whereDate('meal_date', $request->validated('meal_date'))
            ->where('meal_type', $request->validated('meal_type'))
            ->max('position') ?? -1);

        $meal = $owner->meals()->create([
            ...collect($request->validated())->except('plan_user_id')->all(),
            'position' => $nextPosition,
        ]);

        SharedResource::forMealPlan($owner)
            ->announce($request->user(), "ha aggiunto il pasto «{$meal->title}»");

        return back();
    }

    /**
     * Editing a meal already on the board: what it is and who cooks it.
     * Its day and slot are not touched here - those move by drag and drop
     * (see move()).
     */
    public function update(MealUpdateRequest $request, Meal $meal): RedirectResponse
    {
        $meal->update($request->validated());

        SharedResource::forMealPlan($meal->user)
            ->announce($request->user(), "ha modificato il pasto «{$meal->title}»");

        return back();
    }

    /**
     * Puts the meal on one of the plan's people (or on nobody, with a null
     * id) - who's cooking it.
     */
    public function assign(MealAssignRequest $request, Meal $meal): RedirectResponse
    {
        $meal->update(['assigned_to_user_id' => $request->validated('assigned_to_user_id')]);

        $resource = SharedResource::forMealPlan($meal->user);
        $cook = $meal->assigned_to_user_id ? User::query()->find($meal->assigned_to_user_id) : null;

        if ($cook) {
            // Whoever is at the stove hears it addressed to them, the rest
            // of the plan hears who it went to.
            $resource->tell([$cook], $request->user(), "ti ha messo ai fornelli per «{$meal->title}»");
            $resource->tell(
                $resource->people->reject(fn (User $person) => $person->id === $cook->id),
                $request->user(),
                "ha messo {$cook->name} ai fornelli per «{$meal->title}»",
            );
        } else {
            $resource->announce($request->user(), "ha tolto chi cucinava «{$meal->title}»");
        }

        return back();
    }

    /**
     * Shares the user's own meal plan with another user of the platform,
     * found by the email they signed up with (an unknown email is a
     * validation error, not an invite: there is nobody to invite yet).
     */
    public function storeMember(MealPlanMemberStoreRequest $request): RedirectResponse
    {
        $member = User::query()->where('email', $request->validated('email'))->sole();

        $request->user()->mealPlanMembers()->syncWithoutDetaching([$member->id]);

        $request->user()->load('mealPlanMembers');

        SharedResource::forMealPlan($request->user())->invite($member, $request->user());

        return back();
    }

    /**
     * Removes someone from a plan - the owner removing a member, or a
     * member leaving on their own. Whatever they were cooking stays
     * planned, simply with nobody on it.
     */
    public function destroyMember(Request $request, User $owner, User $user): RedirectResponse
    {
        $isOwner = $owner->id === $request->user()->id;
        $isLeaving = $user->id === $request->user()->id;

        abort_unless($isOwner || $isLeaving, 403);
        abort_if($user->id === $owner->id, 403, 'Il proprietario non può essere rimosso dalla sua pianificazione.');

        $owner->mealPlanMembers()->detach($user->id);

        $owner->meals()->where('assigned_to_user_id', $user->id)->update(['assigned_to_user_id' => null]);

        return $isLeaving ? to_route('meals.index') : back();
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

        SharedResource::forMealPlan($meal->user)
            ->announce($request->user(), "ha spostato il pasto «{$meal->title}»");

        return back();
    }

    public function destroy(Request $request, Meal $meal): RedirectResponse
    {
        abort_unless($meal->user->mealPlanIsAccessibleBy($request->user()), 403);

        $meal->delete();

        SharedResource::forMealPlan($meal->user)
            ->announce($request->user(), "ha eliminato il pasto «{$meal->title}»");

        return back();
    }
}
