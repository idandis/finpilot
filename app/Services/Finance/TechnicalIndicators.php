<?php

namespace App\Services\Finance;

use Illuminate\Support\Collection;

class TechnicalIndicators
{
    /**
     * @param  float[]  $closes
     */
    public static function sma(array $closes, int $period): ?float
    {
        if (count($closes) < $period) {
            return null;
        }

        $slice = array_slice($closes, -$period);

        return round(array_sum($slice) / $period, 6);
    }

    /**
     * Standard Wilder-style RSI over the last $period closes.
     *
     * @param  float[]  $closes
     */
    public static function rsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period + 1) {
            return null;
        }

        $gains = [];
        $losses = [];

        for ($i = count($closes) - $period; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = max($change, 0.0);
            $losses[] = max(-$change, 0.0);
        }

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        if ($avgGain === 0.0 && $avgLoss === 0.0) {
            return 50.0;
        }

        if ($avgLoss === 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;

        return round(100 - (100 / (1 + $rs)), 2);
    }

    /**
     * A simple swing-high/swing-low reading: a candle is a swing point only if
     * its high (or low) is strictly the most extreme within $window candles on
     * both sides. Returns the closest swing level above/below $currentPrice,
     * which is a reasonable proxy for "nearest resistance/support" without
     * any real pattern-recognition logic.
     *
     * @param  array<array{high: float, low: float}>  $candles
     * @return array{support: float|null, resistance: float|null}
     */
    public static function nearestSupportResistance(array $candles, float $currentPrice, int $window = 3): array
    {
        $count = count($candles);
        $swingHighs = [];
        $swingLows = [];

        for ($i = $window; $i < $count - $window; $i++) {
            $high = $candles[$i]['high'];
            $low = $candles[$i]['low'];
            $isSwingHigh = true;
            $isSwingLow = true;

            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j === $i) {
                    continue;
                }

                if ($candles[$j]['high'] >= $high) {
                    $isSwingHigh = false;
                }

                if ($candles[$j]['low'] <= $low) {
                    $isSwingLow = false;
                }
            }

            if ($isSwingHigh) {
                $swingHighs[] = $high;
            }

            if ($isSwingLow) {
                $swingLows[] = $low;
            }
        }

        return [
            'support' => Collection::make($swingLows)->filter(fn ($price) => $price < $currentPrice)->sortDesc()->first(),
            'resistance' => Collection::make($swingHighs)->filter(fn ($price) => $price > $currentPrice)->sort()->first(),
        ];
    }

    /**
     * A simple, non-authoritative technical reading (moving average, RSI,
     * nearest swing support/resistance) computed straight from a set of
     * daily candles - no extra data source, no chart-pattern recognition.
     * Shared by every page that shows a candlestick chart (Mercato,
     * Analisi aziende) so the reading is computed identically everywhere.
     *
     * @param  array<int, array{time: string, open: float, high: float, low: float, close: float}>  $candles
     * @return array{sma20: float|null, sma20_signal: string|null, rsi14: float|null, rsi14_signal: string|null, support: float|null, resistance: float|null}
     */
    public static function analyze(array $candles): array
    {
        $closes = array_column($candles, 'close');
        $lastClose = $closes === [] ? null : end($closes);

        $sma20 = self::sma($closes, 20);
        $rsi14 = self::rsi($closes, 14);
        $levels = $lastClose !== null
            ? self::nearestSupportResistance($candles, $lastClose)
            : ['support' => null, 'resistance' => null];

        $sma20Signal = null;

        if ($sma20 !== null && $lastClose !== null) {
            $sma20Signal = $lastClose >= $sma20 ? 'above' : 'below';
        }

        $rsi14Signal = null;

        if ($rsi14 !== null) {
            $rsi14Signal = match (true) {
                $rsi14 >= 70 => 'overbought',
                $rsi14 <= 30 => 'oversold',
                default => 'neutral',
            };
        }

        return [
            'sma20' => $sma20,
            'sma20_signal' => $sma20Signal,
            'rsi14' => $rsi14,
            'rsi14_signal' => $rsi14Signal,
            'support' => $levels['support'],
            'resistance' => $levels['resistance'],
        ];
    }
}
