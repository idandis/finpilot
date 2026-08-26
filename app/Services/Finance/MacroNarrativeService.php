<?php

namespace App\Services\Finance;

/**
 * Turns a bare current/previous pair into the plain-Italian sentence shown
 * on the Macro dashboard, e.g. "L'inflazione USA è scesa dal 3,0% al 2,8%.
 * Il trend è in miglioramento, ma il livello rimane sopra l'obiettivo del
 * 2% della Federal Reserve." Deliberately rule-based (a fixed template plus
 * the indicator's own catalog metadata), not an LLM call - the same
 * explanation must always follow from the same two numbers.
 */
class MacroNarrativeService
{
    /**
     * Values/deltas below this (in the indicator's own unit - almost always
     * percentage points here) are treated as "stable" rather than a real
     * up/down move, since month-to-month noise in these series is common.
     */
    private const STABLE_THRESHOLD = 0.05;

    /**
     * 'improving'|'worsening' when the indicator has a defined good_direction
     * and the latest move is/isn't in that direction; 'neutral' when the
     * indicator has no defined good_direction (e.g. a policy rate, where
     * neither direction is inherently good or bad); 'stable' when the move
     * is below STABLE_THRESHOLD. Exposed separately from explain() so the
     * frontend can color a change badge consistently with the narrative
     * sentence without re-deriving the same rule in TypeScript.
     */
    public function trend(string $indicatorKey, ?float $current, ?float $previous): string
    {
        $meta = MacroIndicators::ALL[$indicatorKey] ?? null;

        if ($meta === null || $current === null || $previous === null) {
            return 'neutral';
        }

        $direction = $this->direction($current, $previous);
        $goodDirection = $meta['good_direction'] ?? null;

        if ($direction === 'stable') {
            return 'stable';
        }

        if ($goodDirection === null) {
            return 'neutral';
        }

        return $direction === $goodDirection ? 'improving' : 'worsening';
    }

    public function explain(string $indicatorKey, ?float $current, ?float $previous): string
    {
        $meta = MacroIndicators::ALL[$indicatorKey] ?? null;

        if ($meta === null || $current === null) {
            return 'Dato non ancora disponibile.';
        }

        $subject = $meta['subject'];
        $unit = $meta['unit'];

        if ($previous === null) {
            return "{$subject} è {$this->formatValue($current, $unit)}.";
        }

        $direction = $this->direction($current, $previous);

        $verb = match ($direction) {
            'up' => 'salito',
            'down' => 'sceso',
            default => 'rimasto stabile',
        };

        // "La curva dei rendimenti", "La disoccupazione" etc. are feminine
        // subjects in Italian, so the participle needs to agree - every
        // subject in the catalog is feminine except the two "Il tasso ..."
        // and "Il rendimento ..." entries, so a simple lookup by leading
        // article is enough rather than tagging gender explicitly per entry.
        if (str_starts_with($subject, 'La ') || str_starts_with($subject, "L'") || str_starts_with($subject, 'Le ')) {
            $verb = match ($direction) {
                'up' => 'salita',
                'down' => 'scesa',
                default => 'rimasta stabile',
            };
        }

        $sentence = $direction === 'stable'
            ? "{$subject} è {$verb} {$this->formatValue($current, $unit)}."
            : "{$subject} è {$verb} da {$this->formatValue($previous, $unit)} a {$this->formatValue($current, $unit)}.";

        if (isset($meta['period_note'])) {
            $sentence = rtrim($sentence, '.')." ({$meta['period_note']}).";
        }

        $sentence .= ' '.$this->trendClause($direction, $meta['good_direction'] ?? null);

        $targetClause = $this->targetClause($current, $meta);

        if ($targetClause !== null) {
            $sentence .= ' '.$targetClause;
        }

        return trim($sentence);
    }

    private function direction(float $current, float $previous): string
    {
        $delta = $current - $previous;

        return abs($delta) < self::STABLE_THRESHOLD ? 'stable' : ($delta > 0 ? 'up' : 'down');
    }

    private function trendClause(string $direction, ?string $goodDirection): string
    {
        if ($direction === 'stable' || $goodDirection === null) {
            return '';
        }

        return $direction === $goodDirection
            ? 'Il trend è in miglioramento.'
            : 'Il trend è in peggioramento.';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function targetClause(float $current, array $meta): ?string
    {
        $target = $meta['target'] ?? null;

        if ($target === null) {
            return null;
        }

        $goodDirection = $meta['good_direction'] ?? null;
        $institution = $meta['target_institution'] ?? null;

        // Only called out when off-target and in the direction that
        // matters (e.g. inflation above a 2% ceiling) - an indicator
        // already at/through its target doesn't need a caveat appended.
        $offTarget = $goodDirection === 'down' && $current > $target + self::STABLE_THRESHOLD;

        if (! $offTarget) {
            return null;
        }

        $targetLabel = rtrim(rtrim(number_format($target, 1, ',', '.'), '0'), ',');
        $suffix = $institution ? " {$institution}" : '';

        return "Il livello rimane sopra l'obiettivo del {$targetLabel}%{$suffix}.";
    }

    private function formatValue(float $value, string $unit): string
    {
        $formatted = number_format($value, 1, ',', '.');

        return $unit === '%' || $unit === 'p.p.' ? "{$formatted}{$unit}" : "{$formatted} {$unit}";
    }
}
