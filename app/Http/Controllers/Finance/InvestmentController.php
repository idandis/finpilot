<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\PortfolioValueHistoryCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Artisan;
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
    public function __construct(
        private readonly InvestmentPositionCalculator $positionCalculator,
        private readonly PortfolioValueHistoryCalculator $historyCalculator,
        private readonly AccountBalanceCalculator $accountBalanceCalculator,
    ) {}

    public function index(Request $request): Response
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->where('name', 'Investimenti')
            ->pluck('id');

        $cards = Card::query()
            ->where('user_id', $request->user()->id)
            ->where('is_investment_card', true)
            ->orderBy('name')
            ->get(['id', 'name', 'financial_account_id']);

        $transactions = Transaction::query()
            ->whereIn('card_id', $cards->pluck('id'))
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get(['transaction_date', 'amount', 'direction', 'card_id', 'isin', 'quantity', 'description']);

        $accountBalances = $this->accountBalanceCalculator->calculate($cards);
        $allAccountBalance = $this->accountBalanceCalculator->totalFor($cards);
        $allPortfolioHistory = $this->historyCalculator->calculate($transactions);

        $tabs = collect([[
            'id' => 'all',
            'name' => 'Tutte le carte',
            'cashFlow' => $this->buildCashFlow($transactions),
            'positions' => $this->positionCalculator->calculate($transactions),
            'portfolioHistory' => $allPortfolioHistory,
            'accountBalance' => $allAccountBalance !== null ? round($allAccountBalance, 2) : null,
            'wealthHistory' => $this->buildWealthHistory($allPortfolioHistory, $cards, $allAccountBalance),
        ]])->concat($cards->map(function (Card $card) use ($transactions, $accountBalances) {
            $cardTransactions = $transactions->where('card_id', $card->id);
            $accountBalance = $card->financial_account_id !== null
                ? round($accountBalances[$card->financial_account_id] ?? 0.0, 2)
                : null;
            $cardPortfolioHistory = $this->historyCalculator->calculate($cardTransactions);

            return [
                'id' => (string) $card->id,
                'name' => $card->name,
                'cashFlow' => $this->buildCashFlow($cardTransactions),
                'positions' => $this->positionCalculator->calculate($cardTransactions),
                'portfolioHistory' => $cardPortfolioHistory,
                'accountBalance' => $accountBalance,
                'wealthHistory' => $this->buildWealthHistory($cardPortfolioHistory, collect([$card]), $accountBalance),
            ];
        }));

        return Inertia::render('finance/Investments/Index', [
            'tabs' => $tabs->values(),
        ]);
    }

    /**
     * Manually trigger the same price refresh the daily scheduler runs -
     * useful right after importing a new statement, without waiting for the
     * next scheduled run. Passes --force so a click always attempts a fresh
     * fetch even within the same 24h window the scheduler itself respects
     * (e.g. a stock move happened but the last fetch was only a few hours
     * ago) - it still shares the same global daily call budget, so clicking
     * this repeatedly can never push EODHD usage past the plan's limit.
     */
    public function refresh(Request $request): RedirectResponse
    {
        Artisan::call('investments:refresh-prices', ['--force' => true]);

        $lines = collect(explode(PHP_EOL, trim(Artisan::output())))->filter();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $lines->last() ?? __('Prices refreshed.'),
        ]);

        return back();
    }

    /**
     * The same weekly dates as the investment portfolio history, but with
     * "invested" replaced by the account's own cash balance as of that date
     * and "market_value" replaced by total wealth (investments + cash) - so
     * the same chart component can plot "andamento del patrimonio" below the
     * investment-only chart. Null when there's no linked account to reconstruct
     * a cash balance for (nothing meaningful to add to the investment chart),
     * matching how `accountBalance` itself is null in that case.
     *
     * @param  array{points: array<int, array{date: string, invested: float, market_value: float|null}>, market_data_since: string|null, unpriced_positions: array<int, array<string, mixed>>}  $portfolioHistory
     * @param  SupportCollection<int, Card>  $cards
     * @return array{points: array<int, array{date: string, invested: float, market_value: float|null}>, market_data_since: string|null, unpriced_positions: array<int, array<string, mixed>>}|null
     */
    private function buildWealthHistory(array $portfolioHistory, SupportCollection $cards, ?float $currentAccountBalance): ?array
    {
        if ($currentAccountBalance === null || empty($portfolioHistory['points'])) {
            return null;
        }

        $dates = collect($portfolioHistory['points'])->pluck('date');
        $balanceByDate = $this->accountBalanceCalculator->historyAsOf($cards, $dates);

        $points = collect($portfolioHistory['points'])->map(function (array $point) use ($balanceByDate) {
            $balance = $balanceByDate[$point['date']] ?? null;

            return [
                'date' => $point['date'],
                'invested' => $balance ?? 0.0,
                'market_value' => ($point['market_value'] !== null && $balance !== null)
                    ? round($point['market_value'] + $balance, 2)
                    : null,
            ];
        })->all();

        return [
            'points' => $points,
            'market_data_since' => $portfolioHistory['market_data_since'],
            'unpriced_positions' => $portfolioHistory['unpriced_positions'],
        ];
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
