<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\InstrumentPrice;
use App\Models\InstrumentPriceHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class InstrumentPriceHistoryRepository
{
    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * One-time backfill of an ISIN's full daily price history, used only by
     * the background refresh command. No-op if already backfilled or if the
     * symbol isn't resolved yet. Costs at most one API call. Only marks
     * history_backfilled_at when the call actually succeeded (even with an
     * empty result, e.g. a young instrument outside the free-tier window) -
     * a failed call is left retryable on the next run.
     */
    public function backfill(InstrumentPrice $record, CarbonInterface $from, CarbonInterface $to): int
    {
        if ($record->history_backfilled_at !== null || $record->code === null || $record->exchange === null) {
            return 0;
        }

        $history = $this->provider->fetchHistory($record->code, $record->exchange, $from, $to);

        if ($history === null) {
            return 1;
        }

        foreach ($history as $point) {
            InstrumentPriceHistory::query()->updateOrCreate(
                ['isin' => $record->isin, 'price_date' => $point->date->format('Y-m-d')],
                ['close_price' => $point->price],
            );
        }

        $record->update(['history_backfilled_at' => now()]);

        return 1;
    }

    /**
     * Full price history for an ISIN, ordered by date - fetched once and
     * reused in memory by the caller, rather than queried per snapshot date.
     *
     * @return Collection<int, InstrumentPriceHistory>
     */
    public function historyFor(string $isin): Collection
    {
        return InstrumentPriceHistory::query()
            ->where('isin', $isin)
            ->orderBy('price_date')
            ->get(['price_date', 'close_price']);
    }
}
