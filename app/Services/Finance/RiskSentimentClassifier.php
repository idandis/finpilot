<?php

namespace App\Services\Finance;

/**
 * Synthesizes a Risk-On/Risk-Off read from indicators already collected by
 * MacroIndicatorRepository::flat() and MarketOverviewRepository::flat() - 5
 * independent signals, each worth +1 (risk-on), -1 (risk-off) or 0
 * (neutral/no data), summed into a single score. Deliberately shows which
 * signals drove the verdict (positive_signals/caution_signals) rather than
 * just a bare score - the same "show your work" principle as
 * EconomicRegimeClassifier.
 */
class RiskSentimentClassifier
{
    /** Treasury 10Y yield level (%) above which financial conditions count as a headwind. */
    private const YIELD_HIGH = 4.0;

    /** Treasury 10Y yield level (%) below which conditions count as accommodative. */
    private const YIELD_LOW = 3.5;

    /**
     * @param  array<string, array<string, mixed>>  $macroIndicators  MacroIndicatorRepository::flat() output
     * @param  array<string, array<string, mixed>>  $marketInstruments  MarketOverviewRepository::flat() output
     * @return array<string, mixed>
     */
    public function classify(array $macroIndicators, array $marketInstruments): array
    {
        $score = 0;
        $positive = [];
        $caution = [];

        $add = function (?bool $isRiskOn, string $onLabel, string $offLabel) use (&$score, &$positive, &$caution) {
            if ($isRiskOn === null) {
                return;
            }

            if ($isRiskOn) {
                $positive[] = $onLabel;
                $score++;
            } else {
                $caution[] = $offLabel;
                $score--;
            }
        };

        $sp500Change = $marketInstruments['sp500']['day_change_percent'] ?? null;
        $add($sp500Change === null || $sp500Change == 0.0 ? null : $sp500Change > 0, 'Azioni in crescita', 'Azioni in calo');

        $vixTrend = $marketInstruments['vix']['trend'] ?? null;
        $add($vixTrend === null || $vixTrend === 'neutral' ? null : $vixTrend === 'down', 'VIX in calo', 'VIX in aumento');

        $yield10y = $macroIndicators['us_yield_10y']['current']['value'] ?? null;
        $add(
            $yield10y === null || ($yield10y < self::YIELD_HIGH && $yield10y >= self::YIELD_LOW) ? null : $yield10y < self::YIELD_LOW,
            'Rendimenti obbligazionari contenuti',
            'Treasury 10Y elevato',
        );

        $eurUsdChange = $marketInstruments['eur_usd']['day_change_percent'] ?? null;
        $add($eurUsdChange === null || $eurUsdChange == 0.0 ? null : $eurUsdChange > 0, 'Dollaro debole', 'Dollaro forte');

        $goldChange = $marketInstruments['gold']['week_change_percent'] ?? null;
        $add($goldChange === null || $goldChange == 0.0 ? null : $goldChange < 0, 'Oro in calo', 'Flussi verso beni rifugio (oro in salita)');

        return [
            'condition' => $this->conditionLabel($score),
            'score' => $score,
            'positive_signals' => $positive,
            'caution_signals' => $caution,
        ];
    }

    private function conditionLabel(int $score): string
    {
        return match (true) {
            $score >= 3 => 'Risk On',
            $score >= 1 => 'Moderatamente Risk On',
            $score <= -3 => 'Risk Off',
            $score <= -1 => 'Moderatamente Risk Off',
            default => 'Neutrale',
        };
    }
}
