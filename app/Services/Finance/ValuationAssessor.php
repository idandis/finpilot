<?php

namespace App\Services\Finance;

/**
 * Compares the current market price against the user's own Fair Value
 * estimate to produce the big "Valutazione Finale" verdict and the
 * "Decisione Operativa" price thresholds. Entirely dependent on Fair Value
 * being filled in (manually, via DCF or otherwise) - there is no way to
 * derive a verdict without it, so everything here is null until both
 * current_price and fair_value are present.
 */
class ValuationAssessor
{
    /**
     * A margin of safety below Fair Value before it's worth starting to
     * buy at all, and a deeper one before accumulating with conviction.
     * Indicative defaults (Buffett-style "margin of safety"), not a
     * precise formula - the user's own Fair Value is already a judgment
     * call, so these just apply a standard cushion to it.
     */
    private const ENTRY_MARGIN = 0.05;

    private const ACCUMULATE_MARGIN = 0.15;

    /**
     * @return array{
     *     deviation_percent: float|null,
     *     verdict: 'undervalued'|'fair'|'expensive'|'very_expensive'|null,
     *     recommended_action: string|null,
     *     entry_price: float|null,
     *     accumulate_price: float|null,
     * }
     */
    public static function assess(?float $currentPrice, ?float $fairValue): array
    {
        if ($fairValue === null || $fairValue <= 0) {
            return [
                'deviation_percent' => null,
                'verdict' => null,
                'recommended_action' => null,
                'entry_price' => null,
                'accumulate_price' => null,
            ];
        }

        $entryPrice = round($fairValue * (1 - self::ENTRY_MARGIN), 2);
        $accumulatePrice = round($fairValue * (1 - self::ACCUMULATE_MARGIN), 2);

        if ($currentPrice === null) {
            return [
                'deviation_percent' => null,
                'verdict' => null,
                'recommended_action' => null,
                'entry_price' => $entryPrice,
                'accumulate_price' => $accumulatePrice,
            ];
        }

        $deviation = round((($currentPrice - $fairValue) / $fairValue) * 100, 2);
        $verdict = self::verdict($deviation);

        return [
            'deviation_percent' => $deviation,
            'verdict' => $verdict,
            'recommended_action' => self::recommendedAction($verdict),
            'entry_price' => $entryPrice,
            'accumulate_price' => $accumulatePrice,
        ];
    }

    /**
     * @return 'undervalued'|'fair'|'expensive'|'very_expensive'
     */
    private static function verdict(float $deviationPercent): string
    {
        return match (true) {
            $deviationPercent <= -15 => 'undervalued',
            $deviationPercent <= 10 => 'fair',
            $deviationPercent <= 30 => 'expensive',
            default => 'very_expensive',
        };
    }

    private static function recommendedAction(string $verdict): string
    {
        return match ($verdict) {
            'undervalued' => 'Compra con decisione',
            'fair' => 'Accumula gradualmente',
            'expensive' => 'Aspetta una correzione',
            'very_expensive' => 'Non comprare ora',
        };
    }
}
