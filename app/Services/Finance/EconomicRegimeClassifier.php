<?php

namespace App\Services\Finance;

/**
 * Classifies the current US macro regime from indicators already collected
 * by MacroIndicatorRepository::flat() - a fixed, ordered rule list (most
 * specific/severe regime checked first, first match wins), never an LLM
 * call. Scoped to US indicators only: they're the most complete series in
 * MacroIndicators::ALL, and mixing USA+Eurozone signals into one regime
 * would blur what each half is actually saying.
 *
 * Each regime also carries a small checklist of characteristic signals
 * (some required to match, some merely corroborating) used only to compute
 * a transparency "confidence" score - how many of that regime's typical
 * traits actually line up - not to decide which regime wins. That decision
 * is made entirely by the ordered rules below.
 */
class EconomicRegimeClassifier
{
    private const GROWTH_STRONG = 2.5;

    private const GROWTH_WEAK = 1.0;

    private const INFLATION_HIGH = 3.0;

    private const INFLATION_LOW = 2.5;

    private const UNEMPLOYMENT_LOW = 4.5;

    private const RATES_ELEVATED = 3.5;

    private const DESCRIPTIONS = [
        'recession' => 'PIL in contrazione e disoccupazione in aumento: la fase più debole del ciclo.',
        'stagflation' => 'Crescita debole e inflazione elevata e in aumento: la banca centrale è stretta tra due fuochi.',
        'non_inflationary_growth' => 'Crescita forte con inflazione e disoccupazione contenute: lo scenario più favorevole.',
        'controlled_slowdown' => 'La crescita rallenta ma l\'inflazione scende: un raffreddamento gestito, non una crisi.',
        'recovery' => 'Il PIL torna ad accelerare e la disoccupazione scende dopo una fase debole.',
        'expansion' => 'Crescita positiva e stabile, senza segnali di surriscaldamento o di frenata.',
        'insufficient_data' => 'Servono più indicatori macro (PIL, inflazione, disoccupazione) per una classificazione.',
    ];

    /**
     * @param  array<string, array<string, mixed>>  $macroIndicators  MacroIndicatorRepository::flat() output
     * @return array<string, mixed>
     */
    public function classify(array $macroIndicators): array
    {
        $gdp = $this->currentValue($macroIndicators, 'us_gdp_growth');
        $gdpTrend = $this->trendOf($macroIndicators, 'us_gdp_growth');
        $inflation = $this->currentValue($macroIndicators, 'us_cpi_yoy');
        $inflationTrend = $this->trendOf($macroIndicators, 'us_cpi_yoy');
        $unemployment = $this->currentValue($macroIndicators, 'us_unemployment');
        $unemploymentTrend = $this->trendOf($macroIndicators, 'us_unemployment');
        $rates = $this->currentValue($macroIndicators, 'us_fed_funds');
        $production = $this->currentValue($macroIndicators, 'us_industrial_production');
        $productionTrend = $this->trendOf($macroIndicators, 'us_industrial_production');

        if ($gdp === null || $inflation === null || $unemployment === null) {
            return $this->result('insufficient_data', 'Dati insufficienti', []);
        }

        $gdpPositive = $gdp > 0;
        $gdpStrong = $gdp >= self::GROWTH_STRONG;
        $gdpWeak = $gdp < self::GROWTH_WEAK;
        $gdpAccelerating = $gdpTrend === 'improving';
        $gdpDecelerating = $gdpTrend === 'worsening';

        $inflationHigh = $inflation >= self::INFLATION_HIGH;
        $inflationLow = $inflation <= self::INFLATION_LOW;
        $inflationFalling = $inflationTrend === 'improving';
        $inflationRising = $inflationTrend === 'worsening';

        $unemploymentLow = $unemployment <= self::UNEMPLOYMENT_LOW;
        $unemploymentRising = $unemploymentTrend === 'worsening';
        $unemploymentFalling = $unemploymentTrend === 'improving';

        $ratesElevated = $rates !== null && $rates >= self::RATES_ELEVATED;
        $productionPositive = $production !== null && $production > 0;
        $productionNegative = $production !== null && $production < 0;
        $productionImproving = $productionTrend === 'improving';

        // Ordered rules, most distinctive/severe first - the first branch
        // whose condition is true wins, later branches are never reached.
        return match (true) {
            ! $gdpPositive && $unemploymentRising => $this->result('recession', 'Recessione', [
                'PIL negativo' => ! $gdpPositive,
                'Disoccupazione in aumento' => $unemploymentRising,
                'Produzione industriale negativa' => $productionNegative,
                'Inflazione non in aumento' => ! $inflationRising,
                'PIL in ulteriore peggioramento' => $gdpDecelerating,
            ]),

            $inflationHigh && $inflationRising && $gdpWeak => $this->result('stagflation', 'Stagflazione', [
                'Inflazione elevata' => $inflationHigh,
                'Inflazione in aumento' => $inflationRising,
                'Crescita debole' => $gdpWeak,
                'Disoccupazione in aumento' => $unemploymentRising,
                'Tassi ufficiali elevati' => $ratesElevated,
            ]),

            $gdpStrong && $inflationLow && $unemploymentLow => $this->result('non_inflationary_growth', 'Crescita non inflazionistica', [
                'Crescita forte' => $gdpStrong,
                'Inflazione contenuta' => $inflationLow,
                'Disoccupazione bassa' => $unemploymentLow,
                'Disoccupazione non in peggioramento' => ! $unemploymentRising,
                'Produzione industriale positiva' => $productionPositive,
            ]),

            $gdpPositive && $gdpDecelerating && $inflationFalling && $ratesElevated => $this->result('controlled_slowdown', 'Rallentamento controllato', [
                'PIL positivo' => $gdpPositive,
                'PIL in decelerazione' => $gdpDecelerating,
                'Inflazione in calo' => $inflationFalling,
                'Tassi ufficiali ancora elevati' => $ratesElevated,
                'Disoccupazione non in peggioramento' => ! $unemploymentRising,
            ]),

            $gdpPositive && $gdpAccelerating && $unemploymentFalling && ! $gdpStrong => $this->result('recovery', 'Ripresa', [
                'PIL positivo' => $gdpPositive,
                'PIL in accelerazione' => $gdpAccelerating,
                'Disoccupazione in calo' => $unemploymentFalling,
                'Crescita non ancora forte' => ! $gdpStrong,
                'Produzione industriale in miglioramento' => $productionImproving,
            ]),

            default => $this->result('expansion', 'Espansione', [
                'PIL positivo' => $gdpPositive,
                'PIL stabile o in accelerazione' => ! $gdpDecelerating,
                'Inflazione moderata' => ! $inflationHigh,
                'Disoccupazione bassa o stabile' => $unemploymentLow || ! $unemploymentRising,
                'Produzione industriale positiva' => $productionPositive,
            ]),
        };
    }

    /**
     * @param  array<string, bool>  $checklist  label => whether that characteristic signal is satisfied
     * @return array<string, mixed>
     */
    private function result(string $key, string $label, array $checklist): array
    {
        $total = count($checklist);
        $satisfied = count(array_filter($checklist));

        return [
            'key' => $key,
            'label' => $label,
            'description' => self::DESCRIPTIONS[$key],
            'confidence_percent' => $total > 0 ? (int) round(($satisfied / $total) * 100) : 0,
            'signals' => collect($checklist)
                ->map(fn (bool $satisfied, string $label) => ['label' => $label, 'satisfied' => $satisfied])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $macroIndicators
     */
    private function currentValue(array $macroIndicators, string $key): ?float
    {
        return $macroIndicators[$key]['current']['value'] ?? null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $macroIndicators
     */
    private function trendOf(array $macroIndicators, string $key): ?string
    {
        return $macroIndicators[$key]['trend'] ?? null;
    }
}
