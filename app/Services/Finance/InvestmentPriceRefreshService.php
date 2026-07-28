<?php

namespace App\Services\Finance;

use App\Models\ExchangeRate;
use App\Models\InstrumentPrice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every open position across every user is collected into a single set of
 * ISINs to refresh - prices are cached globally (not per user), so this has
 * no per-user context and simply works through the whole system's open
 * holdings, stalest first, until the daily call budget runs out. Shared by
 * the scheduled command and the manual "refresh now" button, so both report
 * the same real outcome instead of the button assuming success regardless.
 */
class InvestmentPriceRefreshService
{
    public function __construct(
        private readonly InstrumentPriceRepository $repository,
        private readonly ExchangeRateRepository $rateRepository,
        private readonly InstrumentPriceHistoryRepository $priceHistoryRepository,
        private readonly ExchangeRateHistoryRepository $rateHistoryRepository,
        private readonly InvestmentPositionCalculator $calculator,
        private readonly EodhdCallBudget $budget,
    ) {}

    public function refresh(int $budgetCap, bool $force = false): InvestmentPriceRefreshResult
    {
        $isins = $this->openIsins();

        if ($isins->isEmpty()) {
            return new InvestmentPriceRefreshResult(hadOpenPositions: false);
        }

        // The cap is a per-run limit; the real ceiling is how many calls are
        // actually left today across every entry point (scheduler, manual
        // "refresh now" clicks, ...), tracked globally by EodhdCallBudget.
        $remaining = min($budgetCap, $this->budget->remaining());

        if ($remaining <= 0) {
            return new InvestmentPriceRefreshResult(budgetExhaustedUpfront: true);
        }

        // Refresh known non-EUR currencies first: a single exchange rate
        // unlocks the market value of every instrument priced in it, so
        // it's the highest-leverage spend of the daily budget. On the very
        // first run no instrument currency is known yet, so this is a
        // no-op until the ISIN loop below has resolved at least one symbol.
        $currencies = InstrumentPrice::query()
            ->whereIn('isin', $isins)
            ->whereNotNull('currency')
            ->where('currency', '!=', 'EUR')
            ->distinct()
            ->pluck('currency');

        $cachedRates = ExchangeRate::query()->whereIn('currency', $currencies)->get()->keyBy('currency');

        $orderedCurrencies = $currencies->sortBy(
            fn (string $currency) => $cachedRates->get($currency)?->fetched_at ?? Carbon::createFromTimestamp(0)
        )->values();

        $ratesRefreshed = 0;

        foreach ($orderedCurrencies as $currency) {
            if ($remaining <= 0) {
                break;
            }

            $used = $this->rateRepository->refresh($currency, $remaining, $force);
            $remaining -= $used;

            if ($used > 0) {
                $ratesRefreshed++;
            }
        }

        $cachedPrices = InstrumentPrice::query()->whereIn('isin', $isins)->get()->keyBy('isin');

        $ordered = $isins->sortBy(
            fn (string $isin) => $cachedPrices->get($isin)?->fetched_at ?? Carbon::createFromTimestamp(0)
        )->values();

        $instrumentsRefreshed = 0;

        foreach ($ordered as $isin) {
            if ($remaining <= 0) {
                break;
            }

            $used = $this->repository->refresh($isin, $remaining, $force);
            $remaining -= $used;

            if ($used > 0) {
                $instrumentsRefreshed++;
            }
        }

        // Once the current-price/rate refresh above is done, spend whatever
        // budget is left backfilling full price/rate history - a one-time
        // cost per ISIN/currency (see InstrumentPriceHistoryRepository and
        // ExchangeRateHistoryRepository), used to power the portfolio value
        // chart. Reload from DB: the loops above may have just resolved a
        // previously-unknown ISIN's code/exchange.
        $historyFrom = now()->subYear()->startOfDay();
        $historyTo = now();

        $resolvedRates = ExchangeRate::query()->whereIn('currency', $currencies)->get()->keyBy('currency');

        foreach ($orderedCurrencies as $currency) {
            if ($remaining <= 0) {
                break;
            }

            if ($record = $resolvedRates->get($currency)) {
                $remaining -= $this->rateHistoryRepository->backfill($record, $historyFrom, $historyTo);
            }
        }

        $resolvedInstruments = InstrumentPrice::query()->whereIn('isin', $isins)->get()->keyBy('isin');

        foreach ($ordered as $isin) {
            if ($remaining <= 0) {
                break;
            }

            if ($record = $resolvedInstruments->get($isin)) {
                $remaining -= $this->priceHistoryRepository->backfill($record, $historyFrom, $historyTo);
            }
        }

        return new InvestmentPriceRefreshResult(
            ratesRefreshed: $ratesRefreshed,
            instrumentsRefreshed: $instrumentsRefreshed,
            callsRemaining: $this->budget->remaining(),
        );
    }

    /**
     * Companion to refresh(): keeps a delayed intraday quote alongside the
     * end-of-day close for every already-resolved open position, so the
     * "current price" shown to the user can move throughout the trading
     * day instead of only once daily. Unlike refresh(), there's no
     * scheduler for this - it only runs on demand (the "Aggiorna realtime"
     * button, see InvestmentController::refreshRealtime()) - and it never
     * touches exchange rates or history, only
     * InstrumentPriceRepository::refreshRealtime().
     */
    public function refreshRealtime(int $budgetCap, bool $force = false): InvestmentRealtimePriceRefreshResult
    {
        $isins = $this->openIsins();

        if ($isins->isEmpty()) {
            return new InvestmentRealtimePriceRefreshResult(hadOpenPositions: false);
        }

        $remaining = min($budgetCap, $this->budget->remaining());

        if ($remaining <= 0) {
            return new InvestmentRealtimePriceRefreshResult(budgetExhaustedUpfront: true);
        }

        $cachedPrices = InstrumentPrice::query()->whereIn('isin', $isins)->get()->keyBy('isin');

        $ordered = $isins->sortBy(
            fn (string $isin) => $cachedPrices->get($isin)?->realtime_fetched_at ?? Carbon::createFromTimestamp(0)
        )->values();

        $instrumentsRefreshed = 0;

        foreach ($ordered as $isin) {
            if ($remaining <= 0) {
                break;
            }

            $used = $this->repository->refreshRealtime($isin, $remaining, $force);
            $remaining -= $used;

            if ($used > 0) {
                $instrumentsRefreshed++;
            }
        }

        return new InvestmentRealtimePriceRefreshResult(
            instrumentsRefreshed: $instrumentsRefreshed,
            callsRemaining: $this->budget->remaining(),
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function openIsins(): Collection
    {
        $investmentCategoryIds = TransactionCategory::query()
            ->where('name', 'Investimenti')
            ->pluck('id');

        $transactions = Transaction::query()
            ->whereNotNull('isin')
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get();

        return collect($this->calculator->calculate($transactions)['open'])
            ->pluck('isin')
            ->unique()
            ->values();
    }
}
