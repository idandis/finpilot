<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * Show income/expense totals for every month, grouped by year, once
     * across all of the user's cards and once per individual card - a
     * transaction only counts towards a card's tab if it was imported
     * against that specific card.
     */
    public function index(Request $request): Response
    {
        $cards = Card::query()
            ->whereRelation('financialAccount', 'user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $accountIds = $request->user()->financialAccounts()->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('financial_account_id', $accountIds)
            ->with('category')
            ->get(['id', 'transaction_date', 'amount', 'direction', 'card_id', 'transaction_category_id']);

        $tabs = collect([[
            'id' => 'all',
            'name' => 'Tutte le carte',
            'overview' => $this->buildOverview($transactions),
        ]])->concat($cards->map(fn (Card $card) => [
            'id' => (string) $card->id,
            'name' => $card->name,
            'overview' => $this->buildOverview($transactions->where('card_id', $card->id)),
        ]));

        return Inertia::render('finance/Overview/Index', [
            'tabs' => $tabs->values(),
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
                'categoryBreakdown' => $this->categoryBreakdown($yearTransactions),
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
    private function categoryBreakdown(SupportCollection $transactions): array
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
}
