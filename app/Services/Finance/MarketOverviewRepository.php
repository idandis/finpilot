<?php

namespace App\Services\Finance;

use App\Models\MarketOverviewPriceHistory;
use Illuminate\Support\Collection;

/**
 * Reads market_overview_price_history and turns it into the shape the
 * "Mercati" tab renders: one entry per instrument (current price,
 * day/week/month/quarter/YTD change, distance from 52-week high,
 * volatility, trend, capped sparkline history), grouped by category.
 * Mirrors MacroIndicatorRepository's shape for the sibling "Macro" tab.
 */
class MarketOverviewRepository
{
    /**
     * Raw rows loaded per instrument - the 2-year initial backfill is
     * daily, so this comfortably covers it with margin.
     */
    private const RAW_LIMIT = 600;

    private const HISTORY_LIMIT = 60;

    public function __construct(private readonly MarketOverviewPerformanceCalculator $calculator) {}

    /**
     * @param  array<string, array<string, mixed>>|null  $flat  pass an already-computed flat() result to avoid recomputing it (e.g. when a caller also needs flat() directly)
     * @return array<int, array{key: string, label: string, instruments: array<int, array<string, mixed>>}>
     */
    public function categorized(?array $flat = null): array
    {
        $instruments = $flat ?? $this->flat();
        $categories = [];

        foreach (MarketOverviewInstruments::CATEGORY_LABELS as $categoryKey => $categoryLabel) {
            $inCategory = array_values(array_filter(
                $instruments,
                fn (array $instrument) => MarketOverviewInstruments::ALL[$instrument['key']]['category'] === $categoryKey,
            ));

            if ($inCategory !== []) {
                $categories[] = ['key' => $categoryKey, 'label' => $categoryLabel, 'instruments' => $inCategory];
            }
        }

        return $categories;
    }

    /**
     * Every instrument (current, day/week/month/quarter/YTD change, distance
     * from high, volatility, trend, capped history) keyed by its catalog
     * key, ungrouped - what categorized() groups into sections, and what a
     * rule engine reading a specific instrument by key (e.g.
     * RiskSentimentClassifier wanting 'vix') wants instead.
     *
     * @return array<string, array<string, mixed>>
     */
    public function flat(): array
    {
        $raw = $this->loadRawSeries();
        $instruments = [];

        foreach (MarketOverviewInstruments::ALL as $key => $meta) {
            $instruments[$key] = $this->present($key, $meta, $raw[$key] ?? collect());
        }

        return $instruments;
    }

    /**
     * @return array<string, Collection<int, array{date: string, close: float}>>
     */
    private function loadRawSeries(): array
    {
        $keys = array_keys(MarketOverviewInstruments::ALL);

        $rows = MarketOverviewPriceHistory::query()
            ->whereIn('instrument_key', $keys)
            ->orderByDesc('price_date')
            ->get()
            ->groupBy('instrument_key');

        $series = [];

        foreach ($rows as $key => $group) {
            $series[$key] = $group
                ->take(self::RAW_LIMIT)
                ->sortBy('price_date')
                ->values()
                ->map(fn (MarketOverviewPriceHistory $row) => [
                    'date' => $row->price_date->toDateString(),
                    'close' => (float) $row->close_price,
                ]);
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  Collection<int, array{date: string, close: float}>  $points
     * @return array<string, mixed>
     */
    private function present(string $key, array $meta, Collection $points): array
    {
        $performance = $this->calculator->calculate($points);
        $latest = $points->last();

        return [
            'key' => $key,
            'label' => $meta['label'],
            'region' => $meta['region'],
            'unit' => $meta['unit'],
            'current' => $performance['current'] !== null && $latest !== null
                ? ['value' => $performance['current'], 'date' => $latest['date']]
                : null,
            'day_change_percent' => $performance['day_change_percent'],
            'week_change_percent' => $performance['week_change_percent'],
            'month_change_percent' => $performance['month_change_percent'],
            'quarter_change_percent' => $performance['quarter_change_percent'],
            'ytd_change_percent' => $performance['ytd_change_percent'],
            'distance_from_high_percent' => $performance['distance_from_high_percent'],
            'volatility_percent' => $performance['volatility_percent'],
            'trend' => $performance['trend'],
            'rotation' => $meta['category'] === 'settori'
                ? $this->classifyRotation($performance['quarter_change_percent'], $performance['week_change_percent'])
                : null,
            'history' => $points
                ->slice(-self::HISTORY_LIMIT)
                ->values()
                ->map(fn (array $point) => ['date' => $point['date'], 'value' => $point['close']])
                ->all(),
        ];
    }

    /**
     * A simple 4-quadrant sector-rotation read (only computed for the
     * "settori" category): quarter_change_percent stands in for medium-term
     * strength, week_change_percent for near-term momentum. Deliberately
     * coarse - a real relative-rotation-graph needs a benchmark-relative
     * ratio, not just each sector's own change, but this is enough to tell
     * "strong and still climbing" apart from "strong but fading" using
     * numbers already computed for every other card.
     */
    private function classifyRotation(?float $quarterChangePercent, ?float $weekChangePercent): ?string
    {
        if ($quarterChangePercent === null || $weekChangePercent === null) {
            return null;
        }

        $strong = $quarterChangePercent >= 0;
        $improving = $weekChangePercent >= 0;

        return match (true) {
            $strong && $improving => 'strong_accelerating',
            $strong => 'strong_slowing',
            $improving => 'weak_recovering',
            default => 'weak_worsening',
        };
    }
}
