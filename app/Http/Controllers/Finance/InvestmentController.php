<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\AccountBalanceCalculator;
use App\Services\Finance\InvestmentPositionCalculator;
use App\Services\Finance\InvestmentPriceRefreshResult;
use App\Services\Finance\InvestmentPriceRefreshService;
use App\Services\Finance\InvestmentRealtimePriceRefreshResult;
use App\Services\Finance\PortfolioValueHistoryCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    /**
     * The per-run cap the manual "Aggiorna chiusura" button uses - matches
     * the scheduled command's own default (see RefreshInvestmentPrices), so
     * a click can't outspend a scheduled run's share of the daily budget.
     */
    private const REFRESH_BUDGET_CAP = 18;

    /**
     * Per-run cap the manual "Aggiorna realtime" button uses - matches
     * RefreshRealtimeInvestmentPrices's own default. There's no scheduler
     * for this one (see routes/console.php), so this is the only place
     * that ever spends this budget.
     */
    private const REALTIME_REFRESH_BUDGET_CAP = 200;

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
        private readonly InvestmentPriceRefreshService $refreshService,
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
            $accountKey = $card->financial_account_id ?? "card:{$card->id}";
            $accountBalance = round($accountBalances[$accountKey] ?? 0.0, 2);
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
     * Manually trigger the same end-of-day close refresh the daily
     * scheduler runs - useful right after importing a new statement,
     * without waiting for the next scheduled run. Forces a fresh fetch
     * even within the 24h freshness window the scheduler itself respects
     * (e.g. a stock move happened but the last fetch was only a few hours
     * ago) - it still shares the same global daily call budget, so
     * clicking this repeatedly can never push EODHD usage past the plan's
     * limit. The toast reflects what actually happened (calling the
     * service directly, not via Artisan::call, is what makes that
     * possible) rather than always claiming success, since the budget
     * being exhausted or a symbol failing to resolve are both silent
     * no-ops from the caller's point of view otherwise.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $result = $this->refreshService->refresh(self::REFRESH_BUDGET_CAP, force: true);

        Inertia::flash('toast', [
            'type' => $result->anythingRefreshed() ? 'success' : 'info',
            'message' => $this->refreshToastMessage($result),
        ]);

        return back();
    }

    private function refreshToastMessage(InvestmentPriceRefreshResult $result): string
    {
        if (! $result->hadOpenPositions) {
            return 'Nessuna posizione aperta da aggiornare.';
        }

        if ($result->budgetExhaustedUpfront) {
            return 'Budget giornaliero EODHD già esaurito oggi. Riprova domani.';
        }

        if (! $result->anythingRefreshed()) {
            return 'Nessun prezzo aggiornato: budget esaurito durante il tentativo o simboli non ancora risolvibili.';
        }

        return "Aggiornati {$result->ratesRefreshed} tasso/i di cambio e {$result->instrumentsRefreshed} chiusura/e.";
    }

    /**
     * On-demand delayed intraday quote refresh - unlike refresh(), there's
     * no scheduler for this: it only ever runs when clicked, since keeping
     * it fresh throughout the trading day is exactly what the button is
     * for. Shares the same global daily call budget as everything else
     * EODHD-backed, so clicking repeatedly can't push usage past the
     * plan's limit.
     */
    public function refreshRealtime(Request $request): RedirectResponse
    {
        $result = $this->refreshService->refreshRealtime(self::REALTIME_REFRESH_BUDGET_CAP, force: true);

        Inertia::flash('toast', [
            'type' => $result->anythingRefreshed() ? 'success' : 'info',
            'message' => $this->refreshRealtimeToastMessage($result),
        ]);

        return back();
    }

    private function refreshRealtimeToastMessage(InvestmentRealtimePriceRefreshResult $result): string
    {
        if (! $result->hadOpenPositions) {
            return 'Nessuna posizione aperta da aggiornare.';
        }

        if ($result->budgetExhaustedUpfront) {
            return 'Budget giornaliero EODHD già esaurito oggi. Riprova domani.';
        }

        if (! $result->anythingRefreshed()) {
            return 'Nessuna quotazione aggiornata: budget esaurito durante il tentativo o simboli non ancora risolvibili.';
        }

        return "Aggiornate {$result->instrumentsRefreshed} quotazione/i in tempo reale.";
    }

    /**
     * The same weekly dates as the investment portfolio history, but with
     * "invested" replaced by the card's own cash balance as of that date
     * (zero-based when there's no linked account) and "market_value"
     * replaced by total wealth (investments + cash) - so the same chart
     * component can plot "andamento del patrimonio" below the
     * investment-only chart. Null only when there are no cards at all to
     * chart, matching how `accountBalance` itself is null in that case.
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
