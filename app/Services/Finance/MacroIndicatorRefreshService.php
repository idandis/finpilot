<?php

namespace App\Services\Finance;

use App\Contracts\MacroObservation;
use App\Models\MacroIndicatorObservation;
use Illuminate\Support\Carbon;

/**
 * Pulls fresh observations for every fetchable entry in MacroIndicators::ALL
 * (the "derive"d ones, e.g. the yield curve spread, are computed at read
 * time by MacroIndicatorRepository and never touch this service) and
 * upserts them into macro_indicator_observations. No call budget to track
 * here (unlike EodhdCallBudget) - FRED and the ECB Data Portal are both
 * free with generous rate limits - but macro data only moves monthly at
 * best, so this still only needs to run once a day.
 */
class MacroIndicatorRefreshService
{
    /**
     * On the very first run for an indicator (nothing stored yet), how far
     * back to backfill. Daily series like the Treasury yields go back to
     * the 1960s-80s on FRED - the dashboard only ever displays the most
     * recent ~90 raw points anyway, so pulling the full multi-decade
     * history would just bloat the table for data nothing reads.
     */
    private const INITIAL_BACKFILL_YEARS = 3;

    /**
     * Rows per upsert() call. A single unbounded upsert of a full FRED
     * history (thousands of rows x 6 columns) exceeds MySQL's prepared
     * statement placeholder limit (65535) - chunking keeps every call well
     * under that regardless of how large a single fetch turns out to be.
     */
    private const UPSERT_CHUNK_SIZE = 500;

    public function __construct(
        private readonly FredMacroDataProvider $fred,
        private readonly EcbMacroDataProvider $ecb,
    ) {}

    public function refresh(): MacroIndicatorRefreshResult
    {
        $indicatorsRefreshed = 0;
        $observationsWritten = 0;
        $failed = [];

        foreach (MacroIndicators::fetchable() as $key => $meta) {
            $provider = match ($meta['fetch']['source']) {
                'fred' => $this->fred,
                'ecb' => $this->ecb,
                default => throw new \InvalidArgumentException("Unknown macro data source for indicator [{$key}]."),
            };

            $seriesId = $meta['fetch']['series'];

            if (isset($meta['fetch']['units'])) {
                $seriesId .= '|'.$meta['fetch']['units'];
            }

            $latestDate = MacroIndicatorObservation::query()
                ->where('indicator_key', $key)
                ->max('observation_date');

            // A small overlap window so an already-stored date whose value
            // gets revised upstream is picked up again, not just brand new
            // dates - both FRED and the ECB commonly revise the latest 1-2
            // periods shortly after first publishing them. On the very
            // first run there's nothing to overlap with, so backfill a
            // bounded window instead of the provider's full history.
            $from = $latestDate
                ? Carbon::parse($latestDate)->subMonths(2)
                : now()->subYears(self::INITIAL_BACKFILL_YEARS);

            $observations = $provider->fetchObservations($seriesId, $from);

            if ($observations === null) {
                $failed[] = $key;

                continue;
            }

            if ($observations === []) {
                continue;
            }

            $rows = collect($observations)
                ->map(fn (MacroObservation $observation) => [
                    'indicator_key' => $key,
                    'observation_date' => $observation->date->toDateString(),
                    'value' => $observation->value,
                    'published_at' => $observation->publishedAt?->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
                MacroIndicatorObservation::upsert(
                    $chunk,
                    ['indicator_key', 'observation_date'],
                    ['value', 'published_at', 'updated_at'],
                );
            }

            $indicatorsRefreshed++;
            $observationsWritten += count($rows);
        }

        return new MacroIndicatorRefreshResult(
            indicatorsRefreshed: $indicatorsRefreshed,
            observationsWritten: $observationsWritten,
            failedIndicators: $failed,
        );
    }
}
