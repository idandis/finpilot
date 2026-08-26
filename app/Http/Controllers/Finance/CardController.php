<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CardStoreRequest;
use App\Http\Requests\Finance\CardUpdateRequest;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\SpendingSummaryCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function __construct(
        private readonly SpendingSummaryCalculator $spendingCalculator,
    ) {}

    /**
     * Show all cards belonging to the user, plus a month-by-month
     * income/expense overview per card. Investment activity (buys, sells,
     * dividends...) is deliberately excluded from the overview: it isn't
     * money spent or earned, just cash moved into or out of an asset that
     * can be sold back - that movement has its own dedicated view on the
     * Investments page.
     */
    public function index(Request $request): Response
    {
        $cards = Card::query()
            ->where('user_id', $request->user()->id)
            ->with('financialAccount')
            ->orderBy('name')
            ->get();

        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('card_id', $cards->pluck('id'))
            ->with('category')
            ->get(['id', 'transaction_date', 'amount', 'direction', 'card_id', 'transaction_category_id'])
            ->reject(fn (Transaction $transaction) => $investmentCategoryIds->contains($transaction->transaction_category_id));

        $overviewTabs = $cards->map(fn (Card $card) => [
            'id' => (string) $card->id,
            'name' => $card->name,
            'overview' => $this->buildOverview($transactions->where('card_id', $card->id)),
        ]);

        return Inertia::render('finance/Cards/Index', [
            'cards' => $cards,
            'overviewTabs' => $overviewTabs->values(),
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{year: int, months: SupportCollection<int, array{month: int, income: float, expense: float}>, totals: array{income: float, expense: float}, categoryBreakdown: array<int, array{category_id: int|null, name: string, color: string|null, amount: float}>}>
     */
    private function buildOverview(Collection $transactions): array
    {
        $byYear = $transactions->groupBy(fn (Transaction $transaction) => (int) $transaction->transaction_date->format('Y'));

        $years = $byYear->isEmpty() ? [now()->year] : $byYear->keys()->sortDesc()->values()->all();

        return collect($years)->map(function (int $year) use ($byYear) {
            $yearTransactions = $byYear->get($year, collect());
            $byMonth = $yearTransactions->groupBy(fn (Transaction $transaction) => (int) $transaction->transaction_date->format('n'));

            $months = collect(range(1, 12))->map(function (int $month) use ($byMonth) {
                $monthTransactions = $byMonth->get($month, collect());

                return [
                    'month' => $month,
                    'income' => round((float) $monthTransactions->where('direction', 'income')->sum('amount'), 2),
                    'expense' => round((float) $monthTransactions->where('direction', 'expense')->sum('amount'), 2),
                ];
            })->values();

            return [
                'year' => $year,
                'months' => $months,
                'totals' => [
                    'income' => round($months->sum('income'), 2),
                    'expense' => round($months->sum('expense'), 2),
                ],
                'categoryBreakdown' => $this->yearlyCategoryBreakdown($yearTransactions),
            ];
        })->values()->all();
    }

    /**
     * Each category keeps a fixed, validated color (see TransactionCategorySeeder),
     * so a category's slice color never shifts depending on which other
     * categories happen to have spending in a given period.
     *
     * @param  SupportCollection<int, Transaction>  $transactions
     * @return array<int, array{category_id: int|null, name: string, color: string|null, amount: float}>
     */
    private function yearlyCategoryBreakdown(SupportCollection $transactions): array
    {
        return $transactions
            ->where('direction', 'expense')
            ->groupBy(fn (Transaction $transaction) => $transaction->transaction_category_id ?? 'uncategorized')
            ->map(function ($group, $key) {
                $first = $group->first();

                return [
                    'category_id' => $key === 'uncategorized' ? null : (int) $key,
                    'name' => $first->category->name ?? 'Non categorizzato',
                    'color' => $first->category->color ?? null,
                    'amount' => (float) $group->sum('amount'),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Show the form for creating a new card.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('finance/Cards/Create', [
            'cardTypes' => Card::TYPES,
            'cardIcons' => Card::ICONS,
            'accounts' => $request->user()->financialAccounts()
                ->orderBy('name')
                ->get(['id', 'name', 'bank_name']),
            'preselectedAccountId' => $request->integer('account') ?: null,
        ]);
    }

    /**
     * Show the form for editing an existing card.
     */
    public function edit(Request $request, Card $card): Response
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        return Inertia::render('finance/Cards/Edit', [
            'card' => $card,
            'cardTypes' => Card::TYPES,
            'cardIcons' => Card::ICONS,
            'accounts' => $request->user()->financialAccounts()
                ->orderBy('name')
                ->get(['id', 'name', 'bank_name']),
        ]);
    }

    /**
     * Show a single card: its own transactions (imported specifically
     * against this card) for a given month/year, with the ability to
     * filter, categorize and import a CSV/PDF statement. This works
     * whether or not the card has a linked account.
     */
    public function show(Request $request, Card $card): Response
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $card->load('financialAccount');

        $transactions = Transaction::query()
            ->where('card_id', $card->id)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $categories = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/Cards/Show', [
            'card' => $card,
            'transactions' => $transactions,
            'categories' => $categories,
            'totals' => [
                'income' => (float) $transactions->where('direction', 'income')->sum('amount'),
                'expense' => (float) $transactions->where('direction', 'expense')->sum('amount'),
            ],
            'categoryBreakdown' => $this->categoryBreakdown($transactions, 'expense'),
            'incomeCategoryBreakdown' => $this->categoryBreakdown($transactions, 'income'),
            'budgetComparison' => $this->spendingCalculator->calculate(
                $request->user(),
                collect([$card]),
                sprintf('%04d-%02d', $year, $month),
                $card->id,
            ),
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'cardTransactionsCount' => $card->transactions()->count(),
        ]);
    }

    /**
     * Break down a set of transactions by category for the given direction.
     * Each category keeps a fixed, validated color (see
     * TransactionCategorySeeder), so a category's slice color never shifts
     * depending on which other categories happen to have activity in a
     * given month.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return SupportCollection<int, array{category_id: int|null, name: string, color: string|null, amount: float}>
     */
    private function categoryBreakdown(Collection $transactions, string $direction): SupportCollection
    {
        return $transactions
            ->where('direction', $direction)
            ->groupBy(fn (Transaction $transaction) => $transaction->transaction_category_id ?? 'uncategorized')
            ->map(function ($group, $key) {
                $first = $group->first();

                return [
                    'category_id' => $key === 'uncategorized' ? null : (int) $key,
                    'name' => $first->category->name ?? 'Non categorizzato',
                    'color' => $first->category->color ?? null,
                    'amount' => (float) $group->sum('amount'),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Store a newly created card for the authenticated user.
     */
    public function store(CardStoreRequest $request): RedirectResponse
    {
        $card = $request->user()->cards()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card added.')]);

        return to_route('cards.show', $card);
    }

    /**
     * Update a card.
     */
    public function update(CardUpdateRequest $request, Card $card): RedirectResponse
    {
        $card->fill($request->validated());
        $card->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card updated.')]);

        return to_route('cards.show', $card);
    }

    /**
     * Delete a card.
     */
    public function destroy(Request $request, Card $card): RedirectResponse
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $card->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card deleted.')]);

        return to_route('cards.index');
    }
}
