<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\MarketOverviewPerformanceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MarketOverviewPerformanceCalculatorTest extends TestCase
{
    private function calculator(): MarketOverviewPerformanceCalculator
    {
        return new MarketOverviewPerformanceCalculator;
    }

    /**
     * @param  array<string, float>  $dateToClose
     * @return Collection<int, array{date: string, close: float}>
     */
    private function points(array $dateToClose): Collection
    {
        return collect($dateToClose)
            ->map(fn (float $close, string $date) => ['date' => $date, 'close' => $close])
            ->values();
    }

    public function test_an_empty_series_returns_null_metrics_and_a_neutral_trend()
    {
        $result = $this->calculator()->calculate(collect());

        $this->assertNull($result['current']);
        $this->assertNull($result['day_change_percent']);
        $this->assertSame('neutral', $result['trend']);
    }

    public function test_day_change_percent_compares_the_last_two_points()
    {
        $points = $this->points([
            '2026-07-29' => 100.0,
            '2026-07-30' => 110.0,
        ]);

        $result = $this->calculator()->calculate($points);

        $this->assertSame(110.0, $result['current']);
        $this->assertEqualsWithDelta(10.0, $result['day_change_percent'], 0.01);
    }

    public function test_week_month_quarter_and_ytd_change_use_the_closest_prior_observation()
    {
        // Anchors spaced well apart so "closest observation on/before the
        // target date" is unambiguous for every window regardless of exact
        // day-count arithmetic.
        $points = $this->points([
            '2026-01-01' => 100.0, // YTD baseline (target: Jan 1 of the current year)
            '2026-04-15' => 105.0, // quarter baseline (target: ~90 days back)
            '2026-06-25' => 108.0, // month baseline (target: ~30 days back)
            '2026-07-20' => 109.0, // week baseline (target: ~7 days back)
            '2026-07-29' => 109.5,
            '2026-07-30' => 110.0, // current
        ]);

        $result = $this->calculator()->calculate($points);

        // Rounded to 2 decimals by the calculator (delta covers the rounding).
        $this->assertEqualsWithDelta(0.92, $result['week_change_percent'], 0.01);
        $this->assertEqualsWithDelta(1.85, $result['month_change_percent'], 0.01);
        $this->assertEqualsWithDelta(4.76, $result['quarter_change_percent'], 0.01);
        $this->assertEqualsWithDelta(10.0, $result['ytd_change_percent'], 0.01);
    }

    public function test_distance_from_high_is_negative_when_below_the_52_week_high()
    {
        $points = $this->points([
            '2026-01-01' => 100.0,
            '2026-03-01' => 120.0, // the 52-week high
            '2026-07-30' => 110.0, // current, below it
        ]);

        $result = $this->calculator()->calculate($points);

        $this->assertEqualsWithDelta(-8.33, $result['distance_from_high_percent'], 0.01);
    }

    public function test_distance_from_high_ignores_observations_older_than_a_year()
    {
        $points = $this->points([
            '2024-01-01' => 500.0, // ancient spike, outside the 52-week window
            '2026-06-01' => 108.0,
            '2026-07-30' => 110.0, // current is itself the highest point in-window
        ]);

        $result = $this->calculator()->calculate($points);

        $this->assertEqualsWithDelta(0.0, $result['distance_from_high_percent'], 0.001);
    }

    public function test_volatility_is_null_with_fewer_than_31_points()
    {
        $points = $this->points(
            collect(range(1, 10))
                ->mapWithKeys(fn (int $day) => ["2026-07-{$this->pad($day)}" => 100.0])
                ->all()
        );

        $result = $this->calculator()->calculate($points);

        $this->assertNull($result['volatility_percent']);
    }

    public function test_volatility_is_zero_for_a_perfectly_flat_series()
    {
        $data = [];

        for ($day = 1; $day <= 35; $day++) {
            $date = Carbon::create(2026, 1, 1)->addDays($day - 1)->format('Y-m-d');
            $data[$date] = 100.0;
        }

        $result = $this->calculator()->calculate($this->points($data));

        $this->assertEqualsWithDelta(0.0, $result['volatility_percent'], 0.001);
    }

    public function test_trend_is_up_when_the_current_price_is_above_its_20_day_average()
    {
        $data = [];

        for ($day = 1; $day <= 19; $day++) {
            $date = Carbon::create(2026, 1, 1)->addDays($day - 1)->format('Y-m-d');
            $data[$date] = 100.0;
        }

        $data['2026-01-20'] = 105.0;

        $result = $this->calculator()->calculate($this->points($data));

        $this->assertSame('up', $result['trend']);
    }

    public function test_trend_is_down_when_the_current_price_is_below_its_20_day_average()
    {
        $data = [];

        for ($day = 1; $day <= 19; $day++) {
            $date = Carbon::create(2026, 1, 1)->addDays($day - 1)->format('Y-m-d');
            $data[$date] = 100.0;
        }

        $data['2026-01-20'] = 95.0;

        $result = $this->calculator()->calculate($this->points($data));

        $this->assertSame('down', $result['trend']);
    }

    private function pad(int $day): string
    {
        return str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }
}
