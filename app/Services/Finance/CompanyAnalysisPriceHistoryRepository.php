<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\CompanyAnalysis;
use App\Models\CompanyAnalysisPriceHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CompanyAnalysisPriceHistoryRepository
{
    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * Fetches and caches the full daily OHLC history for a CompanyAnalysis's
     * symbol, throttled to once every 24h since it costs one EODHD API call
     * regardless of range. Returns true when the chart is (already, or now)
     * up to date, false when the call itself failed (missing API key,
     * budget exhausted, provider error) - the caller decides how to surface
     * that. An empty-but-successful result (e.g. a symbol too young for the
     * free-tier window) still counts as success and still updates the
     * fetched-at timestamp, so it isn't retried every click.
     */
    public function refresh(CompanyAnalysis $analysis, CarbonInterface $from, CarbonInterface $to): bool
    {
        if ($analysis->price_history_fetched_at !== null && $analysis->price_history_fetched_at->gt(now()->subDay())) {
            return true;
        }

        [$code, $exchange] = self::splitSymbol($analysis->symbol);
        $history = $this->provider->fetchHistory($code, $exchange, $from, $to);

        if ($history === null) {
            return false;
        }

        // Same reasoning as InstrumentPriceHistoryRepository::backfill(): a
        // raw string match against the `date`-cast price_date column can
        // miss an existing row on SQLite, so dates are compared as plain
        // Y-m-d strings collected up front instead.
        $existingDates = CompanyAnalysisPriceHistory::query()
            ->where('symbol', $analysis->symbol)
            ->pluck('price_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->flip();

        foreach ($history as $point) {
            $dateKey = $point->date->format('Y-m-d');
            $attributes = [
                'close_price' => $point->price,
                'open_price' => $point->open,
                'high_price' => $point->high,
                'low_price' => $point->low,
            ];

            if ($existingDates->has($dateKey)) {
                CompanyAnalysisPriceHistory::query()
                    ->where('symbol', $analysis->symbol)
                    ->whereDate('price_date', $dateKey)
                    ->update($attributes);
            } else {
                CompanyAnalysisPriceHistory::query()->create(['symbol' => $analysis->symbol, 'price_date' => $dateKey, ...$attributes]);
            }
        }

        $analysis->update(['price_history_fetched_at' => now()]);

        return true;
    }

    /**
     * Full price history for a symbol, ordered by date.
     *
     * @return Collection<int, CompanyAnalysisPriceHistory>
     */
    public function historyFor(string $symbol): Collection
    {
        return CompanyAnalysisPriceHistory::query()
            ->where('symbol', $symbol)
            ->orderBy('price_date')
            ->get(['price_date', 'close_price', 'open_price', 'high_price', 'low_price']);
    }

    /**
     * CompanyAnalysis.symbol is either a bare US ticker ("MSFT") or an
     * already EODHD-shaped "{CODE}.{EXCHANGE}" (e.g. "STLA.MI") - unlike the
     * ISIN-based investment portfolio, there's no resolution step needed:
     * a missing exchange suffix just means "US".
     *
     * @return array{0: string, 1: string}
     */
    private static function splitSymbol(string $symbol): array
    {
        if (! str_contains($symbol, '.')) {
            return [$symbol, 'US'];
        }

        $lastDot = strrpos($symbol, '.');

        return [substr($symbol, 0, $lastDot), substr($symbol, $lastDot + 1)];
    }
}
