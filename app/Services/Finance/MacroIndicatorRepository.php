<?php

namespace App\Services\Finance;

use App\Models\MacroIndicatorObservation;
use Illuminate\Support\Carbon;

/**
 * Reads macro_indicator_observations and turns it into the exact shape the
 * "Macro" dashboard renders: one entry per displayable indicator (current,
 * previous, change, capped history, narrative), grouped by category. Also
 * computes every `derive`d entry in MacroIndicators::ALL (the yield curve
 * spread, the Eurozone industrial production YoY rate) from the raw series
 * already loaded - nothing derived is ever stored on its own.
 */
class MacroIndicatorRepository
{
    /**
     * Per-indicator cap on how many raw rows are loaded. Applied uniformly
     * regardless of frequency: for daily series (treasury yields) this is a
     * few months of history, for monthly/quarterly series it's several
     * years - both are plenty for a dashboard sparkline, and a flat row cap
     * is far simpler than a frequency-aware date window.
     */
    private const RAW_LIMIT = 90;

    private const HISTORY_LIMIT = 60;

    public function __construct(private readonly MacroNarrativeService $narrative) {}

    /**
     * @param  array<string, array<string, mixed>>|null  $flat  pass an already-computed flat() result to avoid recomputing it (e.g. when a caller also needs flat() directly)
     * @return array<int, array{key: string, label: string, indicators: array<int, array<string, mixed>>}>
     */
    public function categorized(?array $flat = null): array
    {
        $indicators = $flat ?? $this->flat();
        $categories = [];

        foreach (MacroIndicators::CATEGORY_LABELS as $categoryKey => $categoryLabel) {
            $inCategory = array_values(array_filter(
                $indicators,
                fn (array $indicator) => MacroIndicators::ALL[$indicator['key']]['category'] === $categoryKey,
            ));

            if ($inCategory !== []) {
                $categories[] = ['key' => $categoryKey, 'label' => $categoryLabel, 'indicators' => $inCategory];
            }
        }

        return $categories;
    }

    /**
     * Every displayable indicator (current, previous, change, capped
     * history, narrative, trend) keyed by its catalog key, ungrouped -
     * what categorized() groups into sections, and what a rule engine
     * reading a specific indicator by key (e.g. EconomicRegimeClassifier
     * wanting 'us_gdp_growth') wants instead.
     *
     * @return array<string, array<string, mixed>>
     */
    public function flat(): array
    {
        $raw = $this->loadRawSeries();
        $series = $raw;

        foreach (MacroIndicators::derived() as $key => $meta) {
            $series[$key] = match ($meta['derive']['type']) {
                'spread' => $this->computeSpread($raw, $meta['derive']['minuend'], $meta['derive']['subtrahend']),
                'yoy' => $this->computeYoy($raw[$meta['derive']['from']] ?? []),
                default => [],
            };
        }

        $indicators = [];

        foreach (MacroIndicators::displayable() as $key => $meta) {
            $indicators[$key] = $this->present($key, $meta, $series[$key] ?? []);
        }

        return $indicators;
    }

    /**
     * @return array<string, array<int, array{date: string, value: float, published_at: ?string}>>
     */
    private function loadRawSeries(): array
    {
        $keys = array_keys(MacroIndicators::fetchable());

        $rows = MacroIndicatorObservation::query()
            ->whereIn('indicator_key', $keys)
            ->orderByDesc('observation_date')
            ->get()
            ->groupBy('indicator_key');

        $series = [];

        foreach ($rows as $key => $group) {
            $series[$key] = $group
                ->take(self::RAW_LIMIT)
                ->sortBy('observation_date')
                ->values()
                ->map(fn (MacroIndicatorObservation $row) => [
                    'date' => $row->observation_date->toDateString(),
                    'value' => (float) $row->value,
                    'published_at' => $row->published_at?->toDateString(),
                ])
                ->all();
        }

        return $series;
    }

    /**
     * @param  array<string, array<int, array{date: string, value: float, published_at: ?string}>>  $raw
     * @return array<int, array{date: string, value: float, published_at: ?string}>
     */
    private function computeSpread(array $raw, string $minuendKey, string $subtrahendKey): array
    {
        $subtrahendByDate = collect($raw[$subtrahendKey] ?? [])->keyBy('date');

        return collect($raw[$minuendKey] ?? [])
            ->filter(fn (array $point) => $subtrahendByDate->has($point['date']))
            ->map(fn (array $point) => [
                'date' => $point['date'],
                'value' => $point['value'] - $subtrahendByDate[$point['date']]['value'],
                'published_at' => $point['published_at'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{date: string, value: float, published_at: ?string}>  $points
     * @return array<int, array{date: string, value: float, published_at: ?string}>
     */
    private function computeYoy(array $points): array
    {
        $result = [];

        foreach ($points as $point) {
            $yearAgo = Carbon::parse($point['date'])->subYear();

            // The closest observation within 20 days of exactly one year
            // earlier - monthly/quarterly period-end dates don't fall on
            // the same day every year (28/29/30/31), so an exact date
            // match would silently drop nearly every point.
            $baseline = collect($points)
                ->sortBy(fn (array $candidate) => abs(Carbon::parse($candidate['date'])->diffInDays($yearAgo, false)))
                ->first(fn (array $candidate) => abs(Carbon::parse($candidate['date'])->diffInDays($yearAgo, false)) <= 20);

            if ($baseline === null || (float) $baseline['value'] === 0.0) {
                continue;
            }

            $result[] = [
                'date' => $point['date'],
                'value' => (($point['value'] - $baseline['value']) / abs($baseline['value'])) * 100,
                'published_at' => $point['published_at'],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{date: string, value: float, published_at: ?string}>  $points
     * @return array<string, mixed>
     */
    private function present(string $key, array $meta, array $points): array
    {
        $current = $points === [] ? null : $points[count($points) - 1];
        $previous = count($points) >= 2 ? $points[count($points) - 2] : null;

        $changeAbsolute = ($current && $previous) ? round($current['value'] - $previous['value'], 4) : null;
        $changePercent = ($current && $previous && (float) $previous['value'] !== 0.0)
            ? round((($current['value'] - $previous['value']) / abs($previous['value'])) * 100, 2)
            : null;

        return [
            'key' => $key,
            'label' => $meta['label'],
            'region' => $meta['region'],
            'unit' => $meta['unit'],
            'source' => $this->resolveSource($meta),
            'current' => $current ? ['value' => $current['value'], 'date' => $current['date']] : null,
            'previous' => $previous ? ['value' => $previous['value'], 'date' => $previous['date']] : null,
            'change_absolute' => $changeAbsolute,
            'change_percent' => $changePercent,
            'history' => collect($points)
                ->slice(-self::HISTORY_LIMIT)
                ->map(fn (array $point) => ['date' => $point['date'], 'value' => $point['value']])
                ->values()
                ->all(),
            'published_at' => $current['published_at'] ?? null,
            'narrative' => $this->narrative->explain($key, $current['value'] ?? null, $previous['value'] ?? null),
            'trend' => $this->narrative->trend($key, $current['value'] ?? null, $previous['value'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveSource(array $meta): string
    {
        if (isset($meta['fetch'])) {
            return $meta['fetch']['source'];
        }

        $refKey = $meta['derive']['minuend'] ?? $meta['derive']['from'] ?? null;
        $refMeta = $refKey ? (MacroIndicators::ALL[$refKey] ?? null) : null;

        return $refMeta['fetch']['source'] ?? 'fred';
    }
}
