<?php

namespace App\Services\Finance;

/**
 * A market-sentiment read from indicators already in MarketOverviewRepository
 * ::flat() - VIX level, sector breadth (how many of the 11 SPDR sectors are
 * trending up), and proximity to 52-week highs across indices+sectors.
 *
 * Deliberately narrower than the "textbook" sentiment mix (VIX, breadth,
 * put/call ratio, distance from moving averages, ETF flows, Fear & Greed):
 * put/call ratio and ETF flow data aren't available from any free source,
 * and CNN's Fear & Greed Index has no public API to license - this only
 * ever uses the 3 signals actually computable from data this app already
 * has for free. `omitted_signals` names what's missing so the UI can say so
 * plainly instead of implying full coverage.
 */
class MarketSentimentClassifier
{
    private const VIX_LOW = 15.0;

    private const VIX_HIGH = 25.0;

    private const NEAR_HIGH_THRESHOLD = -5.0;

    /**
     * Shared threshold applied to both breadth measures below (sector
     * breadth out of 11, near-high breadth out of 16) - a ratio, not a
     * fixed count, so it means the same thing regardless of how many
     * instruments are actually being measured.
     */
    private const BREADTH_STRONG_RATIO = 0.65;

    private const BREADTH_WEAK_RATIO = 0.35;

    private const OMITTED_SIGNALS = [
        'Put/call ratio (nessuna fonte gratuita disponibile)',
        'Flussi verso ETF (nessuna fonte gratuita disponibile)',
        'Fear & Greed Index (proprietario CNN, nessuna API pubblica)',
    ];

    /**
     * @param  array<string, array<string, mixed>>  $marketInstruments  MarketOverviewRepository::flat() output
     * @return array<string, mixed>
     */
    public function classify(array $marketInstruments): array
    {
        $score = 0;
        $positive = [];
        $caution = [];

        $add = function (?bool $isPositive, string $onLabel, string $offLabel) use (&$score, &$positive, &$caution) {
            if ($isPositive === null) {
                return;
            }

            if ($isPositive) {
                $positive[] = $onLabel;
                $score++;
            } else {
                $caution[] = $offLabel;
                $score--;
            }
        };

        $vix = $marketInstruments['vix']['current']['value'] ?? null;
        $add(
            $vix === null || ($vix >= self::VIX_LOW && $vix <= self::VIX_HIGH) ? null : $vix < self::VIX_LOW,
            'Volatilità bassa (VIX sotto 15)',
            'Volatilità elevata (VIX sopra 25)',
        );

        // Denominators count only instruments with actual data (a current
        // price / a distance-from-high figure), not the full catalog - a
        // freshly-seeded watchlist with no refresh yet has every trend
        // default to 'neutral', which would otherwise read as "0 up out of
        // 11" (a confidently bearish reading) instead of "no data yet".
        $sectorKeys = collect(MarketOverviewInstruments::ALL)
            ->filter(fn (array $meta) => $meta['category'] === 'settori')
            ->keys();
        $sectorsWithData = $sectorKeys->filter(fn (string $key) => ($marketInstruments[$key]['current'] ?? null) !== null);
        $sectorsUp = $sectorsWithData->filter(fn (string $key) => $marketInstruments[$key]['trend'] === 'up')->count();
        $sectorTotal = $sectorsWithData->count();
        $add(
            $this->breadthSignal($sectorsUp, $sectorTotal),
            "Ampiezza settoriale positiva ({$sectorsUp}/{$sectorTotal} settori in trend rialzista)",
            "Ampiezza settoriale debole ({$sectorsUp}/{$sectorTotal} settori in trend ribassista)",
        );

        $breadthKeys = collect(MarketOverviewInstruments::ALL)
            ->filter(fn (array $meta) => in_array($meta['category'], ['indici', 'settori'], true))
            ->keys();
        $breadthWithData = $breadthKeys->filter(fn (string $key) => ($marketInstruments[$key]['distance_from_high_percent'] ?? null) !== null);
        $nearHigh = $breadthWithData->filter(fn (string $key) => $marketInstruments[$key]['distance_from_high_percent'] >= self::NEAR_HIGH_THRESHOLD)->count();
        $breadthTotal = $breadthWithData->count();
        $add(
            $this->breadthSignal($nearHigh, $breadthTotal),
            "Mercato vicino ai massimi ({$nearHigh}/{$breadthTotal} entro il 5% dal massimo a 52 settimane)",
            "Mercato lontano dai massimi (solo {$nearHigh}/{$breadthTotal} entro il 5% dal massimo)",
        );

        return [
            'sentiment' => $this->sentimentLabel($score),
            'score' => $score,
            'positive_signals' => $positive,
            'caution_signals' => $caution,
            'omitted_signals' => self::OMITTED_SIGNALS,
        ];
    }

    /**
     * True (positive breadth) above the strong ratio, false (weak breadth)
     * below the weak ratio, null (no signal) in the ambiguous middle -
     * total===0 also yields null since there's nothing to measure.
     */
    private function breadthSignal(int $count, int $total): ?bool
    {
        if ($total === 0) {
            return null;
        }

        $ratio = $count / $total;

        if ($ratio >= self::BREADTH_STRONG_RATIO) {
            return true;
        }

        if ($ratio <= self::BREADTH_WEAK_RATIO) {
            return false;
        }

        return null;
    }

    private function sentimentLabel(int $score): string
    {
        return match (true) {
            $score >= 2 => 'Ottimismo',
            $score >= 1 => 'Moderato ottimismo',
            $score <= -2 => 'Pessimismo',
            $score <= -1 => 'Moderato pessimismo',
            default => 'Neutrale',
        };
    }
}
