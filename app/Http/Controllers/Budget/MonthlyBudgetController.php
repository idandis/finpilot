<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\BudgetSubcategory;
use App\Models\MonthlyBudget;
use App\Models\MonthlyBudgetLine;
use App\Services\Budget\BudgetStructureResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;

class MonthlyBudgetController extends Controller
{
    public function __construct(private readonly BudgetStructureResolver $resolver) {}

    public function index(Request $request)
    {
        $now = now();
        $year = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        $user = $request->user();
        $monthlyBudget = $this->resolver->findMonthlyBudget($user, $year, $month);
        $categories = $this->resolver->categoriesFor($user, $monthlyBudget);

        $budgetLines = $monthlyBudget
            ? $monthlyBudget->budgetLines()
                ->get()
                ->mapWithKeys(fn ($line) => [$line->budget_subcategory_id => (float) $line->planned_amount])
            : collect();

        $expenses = $monthlyBudget
            ? $monthlyBudget->expenses()
                ->selectRaw('budget_subcategory_id, SUM(amount) as total_amount')
                ->groupBy('budget_subcategory_id')
                ->get()
                ->mapWithKeys(fn ($expense) => [$expense->budget_subcategory_id => (float) $expense->total_amount])
            : collect();

        $transactions = $monthlyBudget
            ? $monthlyBudget->expenses()
                ->with('subcategory.category')
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BudgetExpense $expense) => [
                    'id' => $expense->id,
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description,
                    'recorded_at' => $expense->recorded_at?->toIso8601String(),
                    'subcategory_id' => $expense->budget_subcategory_id,
                    'subcategory_name' => $expense->subcategory?->name,
                    'category_id' => $expense->subcategory?->category?->id,
                    'category_name' => $expense->subcategory?->category?->name,
                    'category_color' => $expense->subcategory?->category?->color,
                    'direction' => $expense->subcategory?->category?->type ?? BudgetCategory::TYPE_EXPENSE,
                ])
            : collect();

        return Inertia::render('Budget/MonthlyBudget/Index', [
            'year' => $year,
            'month' => $month,
            'categories' => $categories->map(fn (BudgetCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'type' => $category->type,
                'monthly_budget_id' => $category->monthly_budget_id,
                'subcategories' => $category->subcategories->map(fn (BudgetSubcategory $subcategory) => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'monthly_budget_id' => $subcategory->monthly_budget_id,
                ])->values(),
            ])->values(),
            'budgetLines' => $budgetLines,
            'expenses' => $expenses,
            'transactions' => $transactions,
            'monthlyBudget' => $monthlyBudget?->id,
        ]);
    }

    /** Il piano del mese da stampare: categorie, voci e importi attesi. */
    public function pdf(Request $request): HttpResponse
    {
        $now = now();
        $year = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        $user = $request->user();
        $monthlyBudget = $this->resolver->findMonthlyBudget($user, $year, $month);
        $categories = $this->resolver->categoriesFor($user, $monthlyBudget);

        $budgetLines = $monthlyBudget
            ? $monthlyBudget->budgetLines()
                ->get()
                ->mapWithKeys(fn ($line) => [$line->budget_subcategory_id => (float) $line->planned_amount])
            : collect();

        $sections = collect([BudgetCategory::TYPE_INCOME, BudgetCategory::TYPE_EXPENSE])
            ->map(fn (string $type) => [
                'title' => $type === BudgetCategory::TYPE_INCOME ? 'Entrate' : 'Uscite',
                'categories' => $categories
                    ->where('type', $type)
                    ->map(fn (BudgetCategory $category) => [
                        'name' => $category->name,
                        'color' => $category->color,
                        'subcategories' => $category->subcategories
                            ->map(fn (BudgetSubcategory $subcategory) => [
                                'name' => $subcategory->name,
                                'planned' => (float) ($budgetLines[$subcategory->id] ?? 0),
                            ])->values(),
                        'planned' => $category->subcategories->sum(
                            fn (BudgetSubcategory $subcategory) => (float) ($budgetLines[$subcategory->id] ?? 0),
                        ),
                    ])->values(),
            ])
            ->map(fn (array $section) => $section + [
                'planned' => collect($section['categories'])->sum('planned'),
            ]);

        $months = [
            'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
            'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
        ];

        $pdf = Pdf::loadView('budget.pdf', [
            'monthLabel' => "{$months[$month - 1]} {$year}",
            'sections' => $sections,
            'generatedAt' => $now->format('d/m/Y H:i'),
        ]);

        return $pdf->download("budget-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.pdf');
    }

    public function show(Request $request, MonthlyBudget $monthlyBudget)
    {
        abort_unless($monthlyBudget->user_id === $request->user()->id, 403);

        return redirect()->route('monthly-budgets.index', [
            'year' => $monthlyBudget->year,
            'month' => $monthlyBudget->month,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
            'month' => 'required|integer|min:1|max:12',
            'budget_lines' => 'nullable|array',
            'budget_lines.*.subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'budget_lines.*.planned_amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($user, $validated['year'], $validated['month']);

        $this->syncBudgetLines($user->id, $monthlyBudget, $validated['budget_lines'] ?? []);

        return back()->with('success', 'Budget salvato');
    }

    public function update(Request $request, MonthlyBudget $monthlyBudget)
    {
        abort_unless($monthlyBudget->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'budget_lines' => 'array',
            'budget_lines.*.subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'budget_lines.*.planned_amount' => 'required|numeric|min:0',
        ]);

        $this->syncBudgetLines($request->user()->id, $monthlyBudget, $validated['budget_lines'] ?? []);

        return back()->with('success', 'Budget aggiornato');
    }

    public function destroy(Request $request, MonthlyBudget $monthlyBudget)
    {
        abort_unless($monthlyBudget->user_id === $request->user()->id, 403);

        $monthlyBudget->delete();

        return redirect()->route('monthly-budgets.index')
            ->with('success', 'Budget eliminato');
    }

    /**
     * @param  array<int, array{subcategory_id: int, planned_amount: float|int|string}>  $lines
     */
    private function syncBudgetLines(int $userId, MonthlyBudget $monthlyBudget, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        // Solo sottocategorie dell'utente e utilizzabili in questo mese:
        // configurazione comune oppure temporanee di questo stesso mese.
        $allowedIds = BudgetSubcategory::query()
            ->whereIn('id', array_column($lines, 'subcategory_id'))
            ->whereHas('category', fn ($query) => $query->where('user_id', $userId))
            ->where(fn ($query) => $query
                ->whereNull('monthly_budget_id')
                ->orWhere('monthly_budget_id', $monthlyBudget->id))
            ->pluck('id')
            ->all();

        foreach ($lines as $line) {
            if (! in_array((int) $line['subcategory_id'], $allowedIds, true)) {
                continue;
            }

            MonthlyBudgetLine::updateOrCreate(
                [
                    'monthly_budget_id' => $monthlyBudget->id,
                    'budget_subcategory_id' => $line['subcategory_id'],
                ],
                ['planned_amount' => $line['planned_amount']]
            );
        }
    }
}
