<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use App\Models\BudgetSubcategory;
use App\Services\Budget\BudgetOwner;
use App\Services\Budget\BudgetStructureResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BudgetCategoryController extends Controller
{
    public function __construct(private readonly BudgetStructureResolver $resolver) {}

    /** Solo la struttura comune: le voci di un singolo mese si gestiscono dal budget mensile. */
    public function index(Request $request)
    {
        $owner = BudgetOwner::resolve($request->user(), $request->query('budget'));
        $categories = $this->resolver->globalCategories($owner);

        return Inertia::render('Budget/Categories/Index', [
            'categories' => $categories->map(fn (BudgetCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'type' => $category->type,
                'order' => $category->order,
                'is_active' => $category->is_active,
                'subcategories' => $category->subcategories->map(fn (BudgetSubcategory $subcategory) => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'is_active' => $subcategory->is_active,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'type' => ['required', Rule::in(BudgetCategory::TYPES)],
            'scope' => ['required', Rule::in(['global', 'month'])],
            'year' => 'required_if:scope,month|integer|min:2020|max:2099',
            'month' => 'required_if:scope,month|integer|min:1|max:12',
        ]);

        $user = BudgetOwner::resolve($request->user(), $request->input('budget_user_id'));
        $monthlyBudgetId = $validated['scope'] === 'month'
            ? $this->resolver->firstOrCreateMonthlyBudget($user, (int) $validated['year'], (int) $validated['month'])->id
            : null;

        $exists = BudgetCategory::query()
            ->where('user_id', $user->id)
            ->where('monthly_budget_id', $monthlyBudgetId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Esiste già una categoria con questo nome.']);
        }

        $user->budgetCategories()->create([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'type' => $validated['type'],
            'monthly_budget_id' => $monthlyBudgetId,
        ]);

        return back()->with('success', $monthlyBudgetId
            ? 'Categoria creata solo per questo mese'
            : 'Categoria creata con successo');
    }

    public function update(Request $request, BudgetCategory $budgetCategory)
    {
        $this->authorizeCategory($request, $budgetCategory);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
            'is_active' => 'boolean',
        ]);

        $budgetCategory->update($validated);

        return back()->with('success', 'Categoria aggiornata');
    }

    /**
     * Il nuovo ordine delle categorie, come arriva dal trascinamento: la
     * posizione nell'elenco diventa la colonna `order`, usata poi ovunque le
     * categorie vengano lette (configurazione e budget mensile).
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|distinct|exists:budget_categories,id',
        ]);

        $categories = BudgetCategory::query()
            ->whereIn('id', $validated['ids'])
            ->with('user')
            ->get();

        foreach ($categories as $category) {
            $this->authorizeCategory($request, $category);
        }

        foreach ($validated['ids'] as $position => $id) {
            $categories->firstWhere('id', $id)?->update(['order' => $position]);
        }

        return back();
    }

    public function destroy(Request $request, BudgetCategory $budgetCategory)
    {
        $this->authorizeCategory($request, $budgetCategory);

        $budgetCategory->delete();

        return back()->with('success', 'Categoria eliminata');
    }

    public function storeSubcategory(Request $request, BudgetCategory $budgetCategory)
    {
        $this->authorizeCategory($request, $budgetCategory);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scope' => ['required', Rule::in(['global', 'month'])],
            'year' => 'required_if:scope,month|integer|min:2020|max:2099',
            'month' => 'required_if:scope,month|integer|min:1|max:12',
        ]);

        $user = $budgetCategory->user;

        // Una categoria temporanea vive in un solo mese: le sue sottocategorie
        // seguono sempre quello stesso mese.
        $monthlyBudgetId = $budgetCategory->monthly_budget_id
            ?? ($validated['scope'] === 'month'
                ? $this->resolver->firstOrCreateMonthlyBudget($user, (int) $validated['year'], (int) $validated['month'])->id
                : null);

        $exists = $budgetCategory->subcategories()
            ->where('monthly_budget_id', $monthlyBudgetId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Esiste già una sottocategoria con questo nome.']);
        }

        $budgetCategory->subcategories()->create([
            'name' => $validated['name'],
            'monthly_budget_id' => $monthlyBudgetId,
        ]);

        return back()->with('success', $monthlyBudgetId
            ? 'Sottocategoria creata solo per questo mese'
            : 'Sottocategoria creata');
    }

    public function updateSubcategory(Request $request, BudgetSubcategory $subcategory)
    {
        $this->authorizeSubcategory($request, $subcategory);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $subcategory->update($validated);

        return back()->with('success', 'Sottocategoria aggiornata');
    }

    public function destroySubcategory(Request $request, BudgetSubcategory $subcategory)
    {
        $this->authorizeSubcategory($request, $subcategory);

        $subcategory->delete();

        return back()->with('success', 'Sottocategoria eliminata');
    }

    /** Proprietario o invitato: chi condivide il budget può modificarne la struttura. */
    private function authorizeCategory(Request $request, BudgetCategory $category): void
    {
        abort_unless($category->user->budgetIsAccessibleBy($request->user()), 403);
    }

    private function authorizeSubcategory(Request $request, BudgetSubcategory $subcategory): void
    {
        $this->authorizeCategory($request, $subcategory->category);
    }
}
