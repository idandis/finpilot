<?php

namespace App\Services\Finance;

use App\Contracts\MacroDataProvider;
use App\Contracts\MacroObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FredMacroDataProvider implements MacroDataProvider
{
    private const BASE_URL = 'https://api.stlouisfed.org/fred/series/observations';

    public function __construct(private readonly ?string $apiKey) {}

    /**
     * $seriesId is the FRED series id, optionally suffixed with
     * "|{units}" (e.g. "CPIAUCSL|pc1" for a year-over-year percent change
     * instead of the raw index level) - MacroIndicators::ALL['fetch']
     * encodes this via its optional 'units' key, assembled into this
     * combined string by MacroIndicatorRefreshService before the call.
     */
    public function fetchObservations(string $seriesId, ?CarbonInterface $from = null): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        [$series, $units] = array_pad(explode('|', $seriesId, 2), 2, null);

        $response = Http::get(self::BASE_URL, array_filter([
            'series_id' => $series,
            'api_key' => $this->apiKey,
            'file_type' => 'json',
            'units' => $units ?? 'lin',
            'observation_start' => $from?->format('Y-m-d'),
        ]));

        if ($response->failed()) {
            return null;
        }

        $observations = $response->json('observations');

        if (! is_array($observations)) {
            return null;
        }

        return collect($observations)
            // FRED uses the literal string "." for a missing value instead
            // of omitting the row or using null.
            ->filter(fn (array $row) => isset($row['date'], $row['value']) && $row['value'] !== '.')
            ->map(fn (array $row) => new MacroObservation(
                date: Carbon::parse($row['date']),
                value: (float) $row['value'],
                publishedAt: isset($row['realtime_start']) ? Carbon::parse($row['realtime_start']) : null,
            ))
            ->values()
            ->all();
    }
}
