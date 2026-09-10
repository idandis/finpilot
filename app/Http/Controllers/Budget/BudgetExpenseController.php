<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\BudgetSubcategory;
use App\Models\User;
use App\Services\Budget\BudgetAccountBalances;
use App\Services\Budget\BudgetOwner;
use App\Services\Budget\BudgetStructureResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetExpenseController extends Controller
{
    public function __construct(
        private readonly BudgetStructureResolver $resolver,
        private readonly BudgetAccountBalances $balances,
    ) {}

    public function index(Request $request)
    {
        $now = now();
        $year = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        $user = BudgetOwner::resolve($request->user(), $request->query('budget'));
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
                    'financial_account_id' => $expense->financial_account_id,
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
            // I conti sono personali anche quando il budget è condiviso.
            'accounts' => $this->balances->listFor($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
            'budget_subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'financial_account_id' => 'nullable|integer|exists:financial_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'recorded_at' => 'required|date_format:Y-m-d H:i',
        ]);

        $user = BudgetOwner::resolve($request->user(), $request->input('budget_user_id'));
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($user, $validated['year'], $validated['month']);

        if (! $this->subcategoryUsableIn($user->id, (int) $validated['budget_subcategory_id'], $monthlyBudget->id)) {
            return back()->withErrors(['budget_subcategory_id' => 'Sottocategoria non valida per questo mese.']);
        }

        BudgetExpense::create([
            'monthly_budget_id' => $monthlyBudget->id,
            'budget_subcategory_id' => $validated['budget_subcategory_id'],
            'financial_account_id' => $this->accountUsableBy($request->user(), $validated['financial_account_id'] ?? null),
            // Il movimento è del budget, la firma è di chi l'ha scritto: in una
            // modifica successiva non si tocca, resta di chi l'ha registrato.
            'recorded_by_user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'recorded_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Spesa registrata');
    }

    public function update(Request $request, BudgetExpense $budgetExpense)
    {
        abort_unless($budgetExpense->monthlyBudget->user->budgetIsAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
            'budget_subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'financial_account_id' => 'nullable|integer|exists:financial_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'recorded_at' => 'required|date_format:Y-m-d H:i',
        ]);

        $user = $budgetExpense->monthlyBudget->user;
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($user, $validated['year'], $validated['month']);

        if (! $this->subcategoryUsableIn($user->id, (int) $validated['budget_subcategory_id'], $monthlyBudget->id)) {
            return back()->withErrors(['budget_subcategory_id' => 'Sottocategoria non valida per questo mese.']);
        }

        $budgetExpense->update([
            'monthly_budget_id' => $monthlyBudget->id,
            'budget_subcategory_id' => $validated['budget_subcategory_id'],
            'financial_account_id' => $this->accountUsableBy(
                $request->user(),
                $validated['financial_account_id'] ?? null,
                $budgetExpense->financial_account_id,
            ),
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'recorded_at' => $validated['recorded_at'],
        ]);

        return back()->with('success', 'Movimento aggiornato');
    }

    public function destroy(Request $request, BudgetExpense $budgetExpense)
    {
        abort_unless($budgetExpense->monthlyBudget->user->budgetIsAccessibleBy($request->user()), 403);

        $budgetExpense->delete();

        return back()->with('success', 'Spesa eliminata');
    }

    /**
     * Il conto indicato solo se è di chi sta scrivendo il movimento.
     *
     * Un budget condiviso mette in comune le spese, non i conti: ognuno segna
     * le sue con le proprie carte. Il conto di qualcun altro vale come non
     * indicato, cioè come un pagamento in contanti.
     *
     * `$keep` è il conto che il movimento ha già: correggere l'importo di una
     * spesa altrui non deve staccarle il conto di sotto solo perché non è
     * nostro.
     */
    private function accountUsableBy(User $author, mixed $accountId, ?int $keep = null): ?int
    {
        if (! is_numeric($accountId)) {
            return null;
        }

        $accountId = (int) $accountId;

        if ($accountId === $keep) {
            return $accountId;
        }

        return $author->financialAccounts()->whereKey($accountId)->exists()
            ? $accountId
            : null;
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
