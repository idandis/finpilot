<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\TechnicalIndicators;
use Tests\TestCase;

class TechnicalIndicatorsTest extends TestCase
{
    public function test_sma_returns_null_when_there_are_fewer_closes_than_the_period()
    {
        $this->assertNull(TechnicalIndicators::sma([1, 2, 3, 4, 5], 6));
    }

    public function test_sma_averages_the_last_n_closes()
    {
        $this->assertSame(3.0, TechnicalIndicators::sma([1, 2, 3, 4, 5], 5));
        $this->assertSame(5.5, TechnicalIndicators::sma([1, 2, 3, 4, 5, 6], 2));
    }

    public function test_rsi_returns_null_when_there_are_not_enough_closes()
    {
        $this->assertNull(TechnicalIndicators::rsi(array_fill(0, 14, 100.0), 14));
    }

    public function test_rsi_is_100_when_every_period_is_a_gain()
    {
        $closes = range(1, 15);

        $this->assertSame(100.0, TechnicalIndicators::rsi($closes, 14));
    }

    public function test_rsi_is_0_when_every_period_is_a_loss()
    {
        $closes = range(15, 1, 1);

        $this->assertSame(0.0, TechnicalIndicators::rsi($closes, 14));
    }

    public function test_rsi_is_50_on_flat_prices()
    {
        $closes = array_fill(0, 15, 100.0);

        $this->assertSame(50.0, TechnicalIndicators::rsi($closes, 14));
    }

    public function test_nearest_support_resistance_picks_the_closest_swing_levels_around_the_current_price()
    {
        $candles = [
            ['high' => 10, 'low' => 8],
            ['high' => 12, 'low' => 9],
            ['high' => 9, 'low' => 7],
            ['high' => 11, 'low' => 8],
            ['high' => 13, 'low' => 10],
            ['high' => 10, 'low' => 6],
            ['high' => 12, 'low' => 9],
        ];

        $levels = TechnicalIndicators::nearestSupportResistance($candles, 9.5, 1);

        $this->assertEquals(7.0, $levels['support']);
        $this->assertEquals(12.0, $levels['resistance']);
    }

    public function test_nearest_support_resistance_returns_null_when_no_swing_qualifies()
    {
        $candles = [
            ['high' => 10, 'low' => 8],
            ['high' => 12, 'low' => 9],
            ['high' => 9, 'low' => 7],
        ];

        $levels = TechnicalIndicators::nearestSupportResistance($candles, 100.0, 3);

        $this->assertNull($levels['support']);
        $this->assertNull($levels['resistance']);
    }

    public function test_analyze_returns_all_nulls_with_no_candles()
    {
        $result = TechnicalIndicators::analyze([]);

        $this->assertNull($result['sma20']);
        $this->assertNull($result['sma20_signal']);
        $this->assertNull($result['rsi14']);
        $this->assertNull($result['rsi14_signal']);
        $this->assertNull($result['support']);
        $this->assertNull($result['resistance']);
    }

    public function test_analyze_computes_sma_rsi_and_signals_with_enough_history()
    {
        // 25 strictly increasing closes: sma20 averages the last 20, rsi14
        // is 100 since every step is a gain (overbought), price is above
        // its own moving average.
        $candles = [];

        for ($i = 100; $i < 125; $i++) {
            $candles[] = ['time' => "day-{$i}", 'open' => (float) $i, 'high' => (float) $i, 'low' => (float) $i, 'close' => (float) $i];
        }

        $result = TechnicalIndicators::analyze($candles);

        $this->assertSame(114.5, $result['sma20']);
        $this->assertSame('above', $result['sma20_signal']);
        $this->assertSame(100.0, $result['rsi14']);
        $this->assertSame('overbought', $result['rsi14_signal']);
    }
}
