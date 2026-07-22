<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CardStoreRequest;
use App\Http\Requests\Finance\CardUpdateRequest;
use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * Show all cards belonging to the user, across their financial accounts.
     */
    public function index(Request $request): Response
    {
        $cards = Card::query()
            ->whereRelation('financialAccount', 'user_id', $request->user()->id)
            ->with('financialAccount')
            ->get()
            ->sortBy([
                fn (Card $a, Card $b) => $a->financialAccount->name <=> $b->financialAccount->name,
                fn (Card $a, Card $b) => $a->name <=> $b->name,
            ])
            ->values();

        return Inertia::render('finance/Cards/Index', [
            'cards' => $cards,
        ]);
    }

    /**
     * Show a single card: its account's transactions for a given month/year,
     * with the ability to filter, categorize and import a CSV statement.
     */
    public function show(Request $request, Card $card): Response
    {
        abort_unless($card->financialAccount->user_id === $request->user()->id, 403);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $card->load('financialAccount');

        $transactions = Transaction::query()
            ->where('financial_account_id', $card->financial_account_id)
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

        // Each category keeps a fixed, validated color (see TransactionCategorySeeder),
        // so a category's slice color never shifts depending on which other
        // categories happen to have spending in a given month.
        $categoryBreakdown = $transactions
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
            ->values();

        return Inertia::render('finance/Cards/Show', [
            'card' => $card,
            'transactions' => $transactions,
            'categories' => $categories,
            'totals' => [
                'income' => (float) $transactions->where('direction', 'income')->sum('amount'),
                'expense' => (float) $transactions->where('direction', 'expense')->sum('amount'),
            ],
            'categoryBreakdown' => $categoryBreakdown,
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'accountTransactionsCount' => $card->financialAccount->transactions()->count(),
        ]);
    }

    /**
     * Store a newly created card under a financial account.
     */
    public function store(CardStoreRequest $request, FinancialAccount $account): RedirectResponse
    {
        $account->cards()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card added.')]);

        return to_route('accounts.edit', $account);
    }

    /**
     * Update a card.
     */
    public function update(CardUpdateRequest $request, Card $card): RedirectResponse
    {
        $card->fill($request->validated());
        $card->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card updated.')]);

        return to_route('accounts.edit', $card->financial_account_id);
    }

    /**
     * Delete a card.
     */
    public function destroy(Request $request, Card $card): RedirectResponse
    {
        abort_unless($card->financialAccount->user_id === $request->user()->id, 403);

        $accountId = $card->financial_account_id;

        $card->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card deleted.')]);

        return to_route('accounts.edit', $accountId);
    }
}
