<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\BudgetSubcategory;
use App\Services\Budget\BudgetStructureResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetExpenseController extends Controller
{
    public function __construct(private readonly BudgetStructureResolver $resolver) {}

    public function index(Request $request)
    {
        $now = now();
        $year = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        $user = $request->user();
        $monthlyBudget = $this->resolver->findMonthlyBudget($user, $year, $month);
        // Questa pagina resta dedicata alle sole uscite.
        $categories = $this->resolver->categoriesFor($user, $monthlyBudget)
            ->where('type', BudgetCategory::TYPE_EXPENSE);

        $expenses = $monthlyBudget
            ? $monthlyBudget->expenses()
                ->orderByDesc('recorded_at')
                ->get()
                ->map(fn (BudgetExpense $expense) => [
                    'id' => $expense->id,
                    'budget_subcategory_id' => $expense->budget_subcategory_id,
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description,
                    'recorded_at' => $expense->recorded_at,
                ])
            : collect();

        return Inertia::render('Budget/Expenses/Index', [
            'year' => $year,
            'month' => $month,
            'categories' => $categories->map(fn (BudgetCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'monthly_budget_id' => $category->monthly_budget_id,
                'subcategories' => $category->subcategories->map(fn (BudgetSubcategory $subcategory) => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'monthly_budget_id' => $subcategory->monthly_budget_id,
                ])->values(),
            ])->values(),
            'expenses' => $expenses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
            'budget_subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'recorded_at' => 'required|date_format:Y-m-d H:i',
        ]);

        $user = $request->user();
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($user, $validated['year'], $validated['month']);

        if (! $this->subcategoryUsableIn($user->id, (int) $validated['budget_subcategory_id'], $monthlyBudget->id)) {
            return back()->withErrors(['budget_subcategory_id' => 'Sottocategoria non valida per questo mese.']);
        }

        BudgetExpense::create([
            'monthly_budget_id' => $monthlyBudget->id,
            'budget_subcategory_id' => $validated['budget_subcategory_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'recorded_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Spesa registrata');
    }

    public function update(Request $request, BudgetExpense $budgetExpense)
    {
        abort_unless($budgetExpense->monthlyBudget->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
            'budget_subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'recorded_at' => 'required|date_format:Y-m-d H:i',
        ]);

        $user = $request->user();
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($user, $validated['year'], $validated['month']);

        if (! $this->subcategoryUsableIn($user->id, (int) $validated['budget_subcategory_id'], $monthlyBudget->id)) {
            return back()->withErrors(['budget_subcategory_id' => 'Sottocategoria non valida per questo mese.']);
        }

        $budgetExpense->update([
            'monthly_budget_id' => $monthlyBudget->id,
            'budget_subcategory_id' => $validated['budget_subcategory_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'recorded_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Movimento aggiornato');
    }

    public function destroy(Request $request, BudgetExpense $budgetExpense)
    {
        abort_unless($budgetExpense->monthlyBudget->user_id === $request->user()->id, 403);

        $budgetExpense->delete();

        return back()->with('success', 'Spesa eliminata');
    }

    /** La sottocategoria deve essere dell'utente e valida in quel mese. */
    private function subcategoryUsableIn(int $userId, int $subcategoryId, int $monthlyBudgetId): bool
    {
        return BudgetSubcategory::query()
            ->where('id', $subcategoryId)
            ->whereHas('category', fn ($query) => $query->where('user_id', $userId))
            ->where(fn ($query) => $query
                ->whereNull('monthly_budget_id')
                ->orWhere('monthly_budget_id', $monthlyBudgetId))
            ->exists();
    }
}
