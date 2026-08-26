<?php

namespace App\Services\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Models\MarketOverviewPriceHistory;
use Illuminate\Support\Carbon;

/**
 * Refreshes the curated watchlist in MarketOverviewInstruments::ALL - unlike
 * portfolio ISINs (InstrumentPriceHistoryRepository), every entry here is
 * already a known, fixed EODHD ticker, so there's no resolveSymbol() step:
 * fetchHistory() is called directly with the catalog's code/exchange. Draws
 * from the same shared EodhdCallBudget as the portfolio price refresh - at
 * ~11 instruments/day this is a rounding error against that budget.
 */
class MarketOverviewRefreshService
{
    /**
     * On the first run for an instrument, how far back to backfill - enough
     * for a 52-week distance-from-high and a YTD comparison with margin,
     * without pulling an index's entire multi-decade history for data the
     * dashboard never displays past ~90 points.
     */
    private const INITIAL_BACKFILL_YEARS = 2;

    /**
     * Rows per upsert() call - mirrors MacroIndicatorRefreshService's own
     * chunking, which exists because an unbounded upsert of a full history
     * exceeds MySQL's prepared statement placeholder limit.
     */
    private const UPSERT_CHUNK_SIZE = 500;

    public function __construct(private readonly MarketPriceProvider $provider) {}

    public function refresh(): MarketOverviewRefreshResult
    {
        $instrumentsRefreshed = 0;
        $pricesWritten = 0;
        $failed = [];

        foreach (MarketOverviewInstruments::ALL as $key => $meta) {
            $latestDate = MarketOverviewPriceHistory::query()
                ->where('instrument_key', $key)
                ->max('price_date');

            // A short overlap so the latest already-stored day gets
            // refreshed too (EODHD can revise today's close intraday),
            // without re-fetching the full backfill window every run.
            $from = $latestDate
                ? Carbon::parse($latestDate)->subDays(5)
                : now()->subYears(self::INITIAL_BACKFILL_YEARS);

            $history = $this->provider->fetchHistory($meta['code'], $meta['exchange'], $from, now());

            if ($history === null) {
                $failed[] = $key;

                continue;
            }

            if ($history === []) {
                continue;
            }

            $rows = collect($history)
                ->map(fn (FetchedPrice $point) => [
                    'instrument_key' => $key,
                    'price_date' => $point->date->toDateString(),
                    'close_price' => $point->price,
                    'open_price' => $point->open,
                    'high_price' => $point->high,
                    'low_price' => $point->low,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
                MarketOverviewPriceHistory::upsert(
                    $chunk,
                    ['instrument_key', 'price_date'],
                    ['close_price', 'open_price', 'high_price', 'low_price', 'updated_at'],
                );
            }

            $instrumentsRefreshed++;
            $pricesWritten += count($rows);
        }

        return new MarketOverviewRefreshResult(
            instrumentsRefreshed: $instrumentsRefreshed,
            pricesWritten: $pricesWritten,
            failedInstruments: $failed,
        );
    }
}
