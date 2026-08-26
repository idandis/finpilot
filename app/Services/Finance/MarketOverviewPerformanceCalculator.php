<?php

namespace App\Services\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a plain ascending-by-date series of daily closes into the metrics
 * shown per instrument on the "Mercati" tab: day/week/month/quarter/YTD
 * change, distance from the 52-week high, annualized volatility, and a
 * simple trend read. Pure calculation, no I/O - MarketOverviewRepository
 * feeds it the stored history.
 */
class MarketOverviewPerformanceCalculator
{
    private const VOLATILITY_WINDOW = 30;

    private const TRADING_DAYS_PER_YEAR = 252;

    /**
     * @param  Collection<int, array{date: string, close: float}>  $points  ascending by date
     * @return array<string, mixed>
     */
    public function calculate(Collection $points): array
    {
        if ($points->isEmpty()) {
            return [
                'current' => null,
                'day_change_percent' => null,
                'week_change_percent' => null,
                'month_change_percent' => null,
                'quarter_change_percent' => null,
                'ytd_change_percent' => null,
                'distance_from_high_percent' => null,
                'volatility_percent' => null,
                'trend' => 'neutral',
            ];
        }

        $latest = $points->last();
        $current = (float) $latest['close'];
        $currentDate = Carbon::parse($latest['date']);
        $closes = $points->pluck('close')->map(fn ($value) => (float) $value)->all();
        $previous = count($closes) >= 2 ? $closes[count($closes) - 2] : null;

        return [
            'current' => $current,
            'day_change_percent' => $this->percentChange($current, $previous),
            'week_change_percent' => $this->changeSince($points, $currentDate->copy()->subDays(7), $current),
            'month_change_percent' => $this->changeSince($points, $currentDate->copy()->subDays(30), $current),
            'quarter_change_percent' => $this->changeSince($points, $currentDate->copy()->subDays(90), $current),
            'ytd_change_percent' => $this->changeSince($points, Carbon::create($currentDate->year, 1, 1), $current),
            'distance_from_high_percent' => $this->distanceFromHigh($points, $currentDate, $current),
            'volatility_percent' => $this->annualizedVolatility($closes),
            'trend' => $this->trend($closes, $current),
        ];
    }

    private function percentChange(float $current, ?float $baseline): ?float
    {
        if ($baseline === null || $baseline === 0.0) {
            return null;
        }

        return round((($current - $baseline) / abs($baseline)) * 100, 2);
    }

    /**
     * @param  Collection<int, array{date: string, close: float}>  $points
     */
    private function changeSince(Collection $points, Carbon $targetDate, float $current): ?float
    {
        $baseline = $points->last(fn (array $point) => Carbon::parse($point['date'])->lte($targetDate));

        return $baseline ? $this->percentChange($current, (float) $baseline['close']) : null;
    }

    /**
     * @param  Collection<int, array{date: string, close: float}>  $points
     */
    private function distanceFromHigh(Collection $points, Carbon $currentDate, float $current): ?float
    {
        $windowStart = $currentDate->copy()->subYear();
        $high = $points
            ->filter(fn (array $point) => Carbon::parse($point['date'])->gte($windowStart))
            ->max(fn (array $point) => (float) $point['close']);

        return $high ? round((($current - $high) / $high) * 100, 2) : null;
    }

    /**
     * Annualized volatility from the standard deviation of daily log
     * returns over the last VOLATILITY_WINDOW trading days - expressed as a
     * percentage so it reads next to the other *_percent fields.
     *
     * @param  float[]  $closes
     */
    private function annualizedVolatility(array $closes): ?float
    {
        $window = self::VOLATILITY_WINDOW;

        if (count($closes) < $window + 1) {
            return null;
        }

        $slice = array_slice($closes, -($window + 1));
        $returns = [];

        for ($i = 1; $i < count($slice); $i++) {
            if ($slice[$i - 1] <= 0.0) {
                continue;
            }

            $returns[] = log($slice[$i] / $slice[$i - 1]);
        }

        if (count($returns) < 2) {
            return null;
        }

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(fn ($return) => ($return - $mean) ** 2, $returns)) / count($returns);

        return round(sqrt($variance) * sqrt(self::TRADING_DAYS_PER_YEAR) * 100, 2);
    }

    /**
     * A simple "above/below its own 20-day average" read, reusing the same
     * SMA already computed for the portfolio candlestick view
     * (TechnicalIndicators::analyze()) rather than a bespoke measure.
     *
     * @param  float[]  $closes
     */
    private function trend(array $closes, float $current): string
    {
        $sma20 = TechnicalIndicators::sma($closes, 20);

        if ($sma20 === null) {
            return 'neutral';
        }

        return $current >= $sma20 ? 'up' : 'down';
    }
}
