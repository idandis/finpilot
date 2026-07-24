<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Models\InstrumentPrice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\ExchangeRateHistoryRepository;
use App\Services\Finance\ExchangeRateRepository;
use App\Services\Finance\InstrumentPriceHistoryRepository;
use App\Services\Finance\InstrumentPriceRepository;
use App\Services\Finance\InvestmentPositionCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RefreshInvestmentPrices extends Command
{
    /**
     * @var string
     */
    protected $signature = 'investments:refresh-prices {--budget=18} {--force}';

    /**
     * @var string
     */
    protected $description = 'Refresh market prices for ISINs held in open investment positions (EODHD, rate-limited)';

    /**
     * Every open position across every user is collected into a single set
     * of ISINs to refresh - prices are cached globally (not per user), so
     * this command has no per-user context and simply works through the
     * whole system's open holdings, stalest first, until the daily call
     * budget runs out.
     */
    public function handle(
        InstrumentPriceRepository $repository,
        ExchangeRateRepository $rateRepository,
        InstrumentPriceHistoryRepository $priceHistoryRepository,
        ExchangeRateHistoryRepository $rateHistoryRepository,
        InvestmentPositionCalculator $calculator,
        EodhdCallBudget $budget,
    ): int {
        $investmentCategoryIds = TransactionCategory::query()
            ->where('name', 'Investimenti')
            ->pluck('id');

        $transactions = Transaction::query()
            ->whereNotNull('isin')
            ->whereIn('transaction_category_id', $investmentCategoryIds)
            ->get();

        $isins = collect($calculator->calculate($transactions)['open'])
            ->pluck('isin')
            ->unique()
            ->values();

        if ($isins->isEmpty()) {
            $this->info('No open positions to refresh.');

            return self::SUCCESS;
        }

        // The option is a per-run cap; the real ceiling is how many calls are
        // actually left today across every entry point (scheduler, manual
        // "refresh now" clicks, ...), tracked globally by EodhdCallBudget.
        $remaining = min((int) $this->option('budget'), $budget->remaining());

        if ($remaining <= 0) {
            $this->info('Daily EODHD call budget already exhausted today.');

            return self::SUCCESS;
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

        $force = (bool) $this->option('force');
        $ratesRefreshed = 0;

        foreach ($orderedCurrencies as $currency) {
            if ($remaining <= 0) {
                break;
            }

            $used = $rateRepository->refresh($currency, $remaining, $force);
            $remaining -= $used;

            if ($used > 0) {
                $ratesRefreshed++;
            }
        }

        $cachedPrices = InstrumentPrice::query()->whereIn('isin', $isins)->get()->keyBy('isin');

        $ordered = $isins->sortBy(
            fn (string $isin) => $cachedPrices->get($isin)?->fetched_at ?? Carbon::createFromTimestamp(0)
        )->values();

        $refreshed = 0;

        foreach ($ordered as $isin) {
            if ($remaining <= 0) {
                $this->info('Daily budget exhausted, stopping.');
                break;
            }

            $used = $repository->refresh($isin, $remaining, $force);
            $remaining -= $used;

            if ($used > 0) {
                $refreshed++;
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
                $remaining -= $rateHistoryRepository->backfill($record, $historyFrom, $historyTo);
            }
        }

        $resolvedInstruments = InstrumentPrice::query()->whereIn('isin', $isins)->get()->keyBy('isin');

        foreach ($ordered as $isin) {
            if ($remaining <= 0) {
                break;
            }

            if ($record = $resolvedInstruments->get($isin)) {
                $remaining -= $priceHistoryRepository->backfill($record, $historyFrom, $historyTo);
            }
        }

        $this->info("Refreshed {$ratesRefreshed} exchange rate(s) and {$refreshed} instrument(s), {$budget->remaining()} call(s) of budget left today.");

        return self::SUCCESS;
    }
}
