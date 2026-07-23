<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CardStoreRequest;
use App\Http\Requests\Finance\CardUpdateRequest;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * Show all cards belonging to the user.
     */
    public function index(Request $request): Response
    {
        $cards = Card::query()
            ->where('user_id', $request->user()->id)
            ->with('financialAccount')
            ->orderBy('name')
            ->get();

        return Inertia::render('finance/Cards/Index', [
            'cards' => $cards,
        ]);
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
     * @return \Illuminate\Support\Collection<int, array{category_id: int|null, name: string, color: string|null, amount: float}>
     */
    private function categoryBreakdown(Collection $transactions, string $direction): \Illuminate\Support\Collection
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
