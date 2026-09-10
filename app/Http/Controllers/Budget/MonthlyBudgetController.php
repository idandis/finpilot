<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\BudgetMemberStoreRequest;
use App\Models\AccountTransfer;
use App\Models\BudgetCategory;
use App\Models\BudgetExpense;
use App\Models\BudgetSubcategory;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\MonthlyBudgetLine;
use App\Models\User;
use App\Services\Budget\BudgetAccountBalances;
use App\Services\Budget\BudgetOwner;
use App\Services\Budget\BudgetStructureResolver;
use App\Services\Sharing\SharedResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class MonthlyBudgetController extends Controller
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

        $user = $request->user();
        $owner = BudgetOwner::resolve($user, $request->query('budget'));
        $monthlyBudget = $this->resolver->findMonthlyBudget($owner, $year, $month);
        $categories = $this->resolver->categoriesFor($owner, $monthlyBudget);

        $budgetLines = $monthlyBudget
            ? $monthlyBudget->budgetLines()
                ->get()
                ->mapWithKeys(fn ($line) => [$line->budget_subcategory_id => (float) $line->planned_amount])
            : collect();

        // I conti sono personali, ma i movimenti del mese sono di tutti: per
        // sapere quali nascondere servono i conti esclusi di chiunque scriva
        // su questo budget, non solo quelli del proprietario.
        $excludedAccounts = FinancialAccount::query()
            ->whereIn('user_id', $owner->budgetPeople()->pluck('id'))
            ->where('excluded_from_stats', true)
            ->pluck('id');

        $expenses = $monthlyBudget
            ? $monthlyBudget->expenses()
                ->where($this->notOnExcludedAccount('financial_account_id', $excludedAccounts))
                ->selectRaw('budget_subcategory_id, SUM(amount) as total_amount')
                ->groupBy('budget_subcategory_id')
                ->get()
                ->mapWithKeys(fn ($expense) => [$expense->budget_subcategory_id => (float) $expense->total_amount])
            : collect();

        $transactions = $this->timeline($user, $monthlyBudget, $year, $month, $excludedAccounts);

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
            // Le tile e il selettore dei movimenti mostrano i conti di chi
            // guarda: su un budget condiviso ognuno vede i propri.
            'accounts' => $this->balances->listFor($user),
            'budgets' => $this->accessibleBudgets($user),
            'budget' => $this->serializeBudget($owner, $user),
        ]);
    }

    /**
     * Tutto quello che è successo nel mese, dal più recente: i movimenti del
     * budget e i trasferimenti tra conti, in un elenco solo.
     *
     * I trasferimenti non stanno dentro al budget del mese - non sono spese -
     * quindi si pescano per data, e compaiono anche in un mese che di budget
     * non ne ha ancora uno. E siccome spostano soldi fra conti, che sono
     * personali, si vedono solo i propri: i movimenti del budget invece sono
     * di tutti quelli che ci lavorano.
     *
     * @param  Collection<int, int>  $excludedAccounts
     * @return Collection<int, array<string, mixed>>
     */
    private function timeline(User $viewer, ?MonthlyBudget $monthlyBudget, int $year, int $month, Collection $excludedAccounts)
    {
        $movements = $monthlyBudget
            ? $monthlyBudget->expenses()
                ->where($this->notOnExcludedAccount('financial_account_id', $excludedAccounts))
                ->with(['subcategory.category', 'financialAccount', 'recordedBy'])
                ->get()
                ->map(fn (BudgetExpense $expense) => [
                    'id' => $expense->id,
                    'kind' => 'movement',
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description,
                    'recorded_at' => $expense->recorded_at?->toIso8601String(),
                    'subcategory_id' => $expense->budget_subcategory_id,
                    'subcategory_name' => $expense->subcategory?->name,
                    'category_id' => $expense->subcategory?->category?->id,
                    'category_name' => $expense->subcategory?->category?->name,
                    'category_color' => $expense->subcategory?->category?->color,
                    'direction' => $expense->subcategory?->category?->type ?? BudgetCategory::TYPE_EXPENSE,
                    'account_id' => $expense->financial_account_id,
                    'account_name' => $expense->financialAccount?->name,
                    'account_color' => $expense->financialAccount?->color,
                    'recorded_by_id' => $expense->recorded_by_user_id,
                    'recorded_by_name' => $expense->recordedBy?->name,
                    'to_account_id' => null,
                    'to_account_name' => null,
                    'to_account_color' => null,
                ])
            : collect();

        $transfers = $viewer->accountTransfers()
            ->with(['fromAccount', 'toAccount', 'recordedBy'])
            ->whereYear('transferred_at', $year)
            ->whereMonth('transferred_at', $month)
            ->where($this->notOnExcludedAccount('from_financial_account_id', $excludedAccounts))
            ->where($this->notOnExcludedAccount('to_financial_account_id', $excludedAccounts))
            ->get()
            ->map(fn (AccountTransfer $transfer) => [
                'id' => $transfer->id,
                'kind' => 'transfer',
                'amount' => (float) $transfer->amount,
                'description' => $transfer->description,
                'recorded_at' => $transfer->transferred_at->toIso8601String(),
                'subcategory_id' => null,
                'subcategory_name' => null,
                'category_id' => null,
                'category_name' => null,
                'category_color' => null,
                'direction' => 'transfer',
                'account_id' => $transfer->from_financial_account_id,
                'account_name' => $transfer->fromAccount?->name,
                'account_color' => $transfer->fromAccount?->color,
                'recorded_by_id' => $transfer->recorded_by_user_id,
                'recorded_by_name' => $transfer->recordedBy?->name,
                'to_account_id' => $transfer->to_financial_account_id,
                'to_account_name' => $transfer->toAccount?->name,
                'to_account_color' => $transfer->toAccount?->color,
            ]);

        return $movements->concat($transfers)
            ->sortByDesc(fn (array $entry) => [$entry['recorded_at'] ?? '', $entry['id']])
            ->values();
    }

    /**
     * Un movimento passato da un conto escluso dalle statistiche, per il
     * budget non è mai successo: fuori dallo speso e fuori dall'elenco del
     * mese. Un trasferimento basta che tocchi il conto da una delle due parti.
     *
     * La colonna può essere vuota - un movimento in contanti, un giro verso
     * "non indicato" - e in SQL `NULL NOT IN (…)` non è vero: senza il ramo
     * sul nullo quei movimenti sparirebbero insieme agli altri.
     *
     * @param  Collection<int, int>  $excluded
     * @return \Closure(Builder): void
     */
    private function notOnExcludedAccount(string $column, Collection $excluded): \Closure
    {
        return function ($query) use ($column, $excluded) {
            if ($excluded->isEmpty()) {
                return;
            }

            $query->whereNull($column)->orWhereNotIn($column, $excluded);
        };
    }

    /**
     * Il selettore: prima il proprio budget, poi quelli condivisi, ognuno
     * identificato dal suo proprietario.
     *
     * @return Collection<int, array{id: int, name: string, is_shared: bool, is_default: bool}>
     */
    private function accessibleBudgets(User $user)
    {
        // Nessuna scelta vale come "il mio": è quello che si apriva prima che
        // il predefinito esistesse.
        $default = $user->default_budget_user_id ?? $user->id;

        return collect([[
            'id' => $user->id,
            'name' => 'Il mio budget',
            'is_shared' => false,
            'is_default' => $default === $user->id,
        ]])->concat($user->sharedBudgets()->orderBy('name')->get()
            ->map(fn (User $owner) => [
                'id' => $owner->id,
                'name' => $owner->name,
                'is_shared' => true,
                'is_default' => $default === $owner->id,
            ]));
    }

    /**
     * Il budget che si apre all'avvio.
     *
     * Si può eleggere anche uno condiviso: chi tiene i conti di casa su quello
     * dell'altra persona non deve sceglierlo dal menù ogni volta. Tornare al
     * proprio è la stessa azione con il proprio id, che si salva come nessuna
     * scelta.
     */
    public function setDefaultBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'budget_user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $chosen = (int) $validated['budget_user_id'];

        if ($chosen === $user->id) {
            $user->update(['default_budget_user_id' => null]);

            return back();
        }

        // Solo fra quelli condivisi con lui: il budget di un estraneo non si
        // elegge a predefinito nemmeno provandoci a mano.
        abort_unless($user->sharedBudgets()->whereKey($chosen)->exists(), 403);

        $user->update(['default_budget_user_id' => $chosen]);

        return back();
    }

    /**
     * Il budget mostrato con tutte le persone che ci lavorano: la lista
     * dietro il pannello di condivisione.
     *
     * @return array<string, mixed>
     */
    private function serializeBudget(User $owner, User $user): array
    {
        $owner->load('budgetMembers:id,name,email');

        return [
            'id' => $owner->id,
            'name' => $owner->id === $user->id ? 'Il mio budget' : $owner->name,
            'is_owner' => $owner->id === $user->id,
            'people' => $owner->budgetPeople()->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'is_owner' => $person->id === $owner->id,
            ])->all(),
        ];
    }

    public function storeMember(BudgetMemberStoreRequest $request): RedirectResponse
    {
        $member = User::query()->where('email', $request->validated('email'))->sole();

        $request->user()->budgetMembers()->syncWithoutDetaching([$member->id]);
        $request->user()->load('budgetMembers');

        SharedResource::forBudget($request->user())->invite($member, $request->user());

        return back();
    }

    /**
     * Toglie qualcuno da un budget: il proprietario che rimuove un membro,
     * oppure un membro che esce da solo.
     */
    public function destroyMember(Request $request, User $owner, User $user): RedirectResponse
    {
        $isOwner = $owner->id === $request->user()->id;
        $isLeaving = $user->id === $request->user()->id;

        abort_unless($isOwner || $isLeaving, 403);
        abort_if($user->id === $owner->id, 403, 'Il proprietario non può essere rimosso dal suo budget.');

        $owner->budgetMembers()->detach($user->id);

        return $isLeaving ? to_route('monthly-budgets.index') : back();
    }

    /** Il piano del mese da stampare: categorie, voci e importi attesi. */
    public function pdf(Request $request): HttpResponse
    {
        $now = now();
        $year = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        $user = $request->user();
        $owner = BudgetOwner::resolve($user, $request->query('budget'));
        $monthlyBudget = $this->resolver->findMonthlyBudget($owner, $year, $month);
        $categories = $this->resolver->categoriesFor($owner, $monthlyBudget);

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
        abort_unless($monthlyBudget->user->budgetIsAccessibleBy($request->user()), 403);

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

        $owner = BudgetOwner::resolve($request->user(), $request->input('budget_user_id'));
        $monthlyBudget = $this->resolver->firstOrCreateMonthlyBudget($owner, $validated['year'], $validated['month']);

        $this->syncBudgetLines($owner->id, $monthlyBudget, $validated['budget_lines'] ?? []);

        return back()->with('success', 'Budget salvato');
    }

    public function update(Request $request, MonthlyBudget $monthlyBudget)
    {
        abort_unless($monthlyBudget->user->budgetIsAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'budget_lines' => 'array',
            'budget_lines.*.subcategory_id' => 'required|integer|exists:budget_subcategories,id',
            'budget_lines.*.planned_amount' => 'required|numeric|min:0',
        ]);

        $this->syncBudgetLines($monthlyBudget->user_id, $monthlyBudget, $validated['budget_lines'] ?? []);

        return back()->with('success', 'Budget aggiornato');
    }

    public function destroy(Request $request, MonthlyBudget $monthlyBudget)
    {
        abort_unless($monthlyBudget->user->budgetIsAccessibleBy($request->user()), 403);

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
