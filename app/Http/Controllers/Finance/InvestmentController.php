<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\InvestmentPositionCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    /**
     * Show, per year and per card, how much cash went into investments
     * (buys, savings plan executions, portfolio tax) versus how much came
     * back out (sells, dividends, interest, cashback) - a cash-flow view,
     * not a real gain/loss: it says nothing about what unsold holdings are
     * worth today.
     */
    public function __construct(private readonly InvestmentPositionCalculator $positionCalculator) {}

    public function index(Request $request): Response
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $cards = Card::query()
            ->whereRelation('financialAccount', 'user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $accountIds = $request->user()->financialAccounts()->pluck('id');

        $transactions = Transaction::query()
            ->whereIn('financial_account_id', $accountIds)
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        $tabs = collect([[
            'id' => 'all',
            'name' => 'Tutte le carte',
            'cashFlow' => $this->buildCashFlow($transactions),
            'positions' => $this->positionCalculator->calculate($transactions),
        ]])->concat($cards->map(fn (Card $card) => [
            'id' => (string) $card->id,
            'name' => $card->name,
            'cashFlow' => $this->buildCashFlow($transactions->where('card_id', $card->id)),
            'positions' => $this->positionCalculator->calculate($transactions->where('card_id', $card->id)),
        ]));

        return Inertia::render('finance/Investments/Index', [
            'tabs' => $tabs->values(),
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{year: int, months: array<int, array{month: int, versato: float, rientrato: float}>, totals: array{versato: float, rientrato: float}}>
     */
    private function buildCashFlow(Collection $transactions): array
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
                    'versato' => round((float) $monthTransactions->where('direction', 'expense')->sum('amount'), 2),
                    'rientrato' => round((float) $monthTransactions->where('direction', 'income')->sum('amount'), 2),
                ];
            })->values()->all();

            return [
                'year' => $year,
                'months' => $months,
                'totals' => [
                    'versato' => round((float) collect($months)->sum('versato'), 2),
                    'rientrato' => round((float) collect($months)->sum('rientrato'), 2),
                ],
            ];
        })->values()->all();
    }
}
