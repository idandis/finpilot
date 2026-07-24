<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ExchangeRateHistoryRepository
{
    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * One-time backfill of a currency's full daily rate history, used only
     * by the background refresh command. No-op if already backfilled. Costs
     * at most one API call. Only marks history_backfilled_at when the call
     * actually succeeded (even with an empty result) - a failed call is
     * left retryable on the next run.
     */
    public function backfill(ExchangeRate $record, CarbonInterface $from, CarbonInterface $to): int
    {
        if ($record->history_backfilled_at !== null) {
            return 0;
        }

        $history = $this->provider->fetchHistory("{$record->currency}EUR", 'FOREX', $from, $to);

        if ($history === null) {
            return 1;
        }

        foreach ($history as $point) {
            ExchangeRateHistory::query()->updateOrCreate(
                ['currency' => $record->currency, 'rate_date' => $point->date->format('Y-m-d')],
                ['rate_to_eur' => $point->price],
            );
        }

        $record->update(['history_backfilled_at' => now()]);

        return 1;
    }

    /**
     * Full rate history for a currency, ordered by date - fetched once and
     * reused in memory by the caller, rather than queried per snapshot date.
     *
     * @return Collection<int, ExchangeRateHistory>
     */
    public function historyFor(string $currency): Collection
    {
        return ExchangeRateHistory::query()
            ->where('currency', $currency)
            ->orderBy('rate_date')
            ->get(['rate_date', 'rate_to_eur']);
    }
}
