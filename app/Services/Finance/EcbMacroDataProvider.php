<?php

namespace App\Services\Finance;

use App\Contracts\MacroDataProvider;
use App\Contracts\MacroObservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class EcbMacroDataProvider implements MacroDataProvider
{
    private const BASE_URL = 'https://data-api.ecb.europa.eu/service/data';

    /**
     * $seriesId is "{flowRef}/{key}" (e.g.
     * "ICP/M.U2.N.000000.4.ANR") - the ECB SDMX REST convention, see
     * MacroIndicators::ALL for the ones this app tracks. No API key: the
     * ECB Data Portal is public.
     */
    public function fetchObservations(string $seriesId, ?CarbonInterface $from = null): ?array
    {
        $response = Http::get(self::BASE_URL."/{$seriesId}", array_filter([
            'format' => 'csvdata',
            'startPeriod' => $from?->format('Y-m-d'),
        ]));

        if ($response->failed() || trim((string) $response->body()) === '') {
            return null;
        }

        return $this->parseCsv($response->body());
    }

    /**
     * @return array<int, MacroObservation>
     */
    private function parseCsv(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        $header = str_getcsv(array_shift($lines) ?? '');

        $periodIndex = array_search('TIME_PERIOD', $header, true);
        $valueIndex = array_search('OBS_VALUE', $header, true);

        if ($periodIndex === false || $valueIndex === false) {
            return [];
        }

        $observations = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $period = $row[$periodIndex] ?? null;
            $value = $row[$valueIndex] ?? null;

            if ($period === null || $value === null || $value === '') {
                continue;
            }

            $date = $this->periodToDate($period);

            if ($date === null) {
                continue;
            }

            $observations[] = new MacroObservation(date: $date, value: (float) $value);
        }

        return $observations;
    }

    /**
     * Converts an SDMX TIME_PERIOD into the last calendar day of that
     * period, so a monthly/quarterly/annual observation always lands on a
     * concrete date - matching how observation_date is stored for the FRED
     * side (which reports actual daily/monthly dates already).
     */
    private function periodToDate(string $period): ?Carbon
    {
        if (preg_match('/^(\d{4})$/', $period, $m)) {
            return Carbon::createFromDate((int) $m[1], 12, 31);
        }

        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            return Carbon::createFromDate((int) $m[1], ((int) $m[2]) * 3, 1)->endOfMonth();
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->endOfMonth();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return Carbon::parse($period);
        }

        return null;
    }
}
