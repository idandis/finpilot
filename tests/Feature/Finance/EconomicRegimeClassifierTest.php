<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\EconomicRegimeClassifier;
use Tests\TestCase;

class EconomicRegimeClassifierTest extends TestCase
{
    private function classifier(): EconomicRegimeClassifier
    {
        return new EconomicRegimeClassifier;
    }

    /**
     * @return array<string, mixed>
     */
    private function indicator(?float $value, ?string $trend = null): array
    {
        return [
            'current' => $value !== null ? ['value' => $value] : null,
            'trend' => $trend,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function macro(
        float $gdp,
        ?string $gdpTrend,
        float $inflation,
        ?string $inflationTrend,
        float $unemployment,
        ?string $unemploymentTrend,
        ?float $rates = null,
        ?float $production = null,
        ?string $productionTrend = null,
    ): array {
        return [
            'us_gdp_growth' => $this->indicator($gdp, $gdpTrend),
            'us_cpi_yoy' => $this->indicator($inflation, $inflationTrend),
            'us_unemployment' => $this->indicator($unemployment, $unemploymentTrend),
            'us_fed_funds' => $this->indicator($rates),
            'us_industrial_production' => $this->indicator($production, $productionTrend),
        ];
    }

    public function test_negative_gdp_with_rising_unemployment_is_a_recession()
    {
        $macro = $this->macro(
            gdp: -1.5, gdpTrend: 'worsening',
            inflation: 2.0, inflationTrend: 'stable',
            unemployment: 5.5, unemploymentTrend: 'worsening',
            production: -0.5,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('recession', $regime['key']);
        $this->assertSame('Recessione', $regime['label']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_high_rising_inflation_with_weak_growth_is_stagflation()
    {
        $macro = $this->macro(
            gdp: 0.5, gdpTrend: 'stable',
            inflation: 4.0, inflationTrend: 'worsening',
            unemployment: 5.0, unemploymentTrend: 'worsening',
            rates: 4.5,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('stagflation', $regime['key']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_strong_growth_with_low_inflation_and_unemployment_is_non_inflationary_growth()
    {
        $macro = $this->macro(
            gdp: 3.0, gdpTrend: 'stable',
            inflation: 2.0, inflationTrend: 'neutral',
            unemployment: 3.8, unemploymentTrend: 'stable',
            production: 1.5,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('non_inflationary_growth', $regime['key']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_decelerating_growth_with_falling_inflation_and_elevated_rates_is_a_controlled_slowdown()
    {
        $macro = $this->macro(
            gdp: 1.5, gdpTrend: 'worsening',
            inflation: 2.5, inflationTrend: 'improving',
            unemployment: 4.0, unemploymentTrend: 'stable',
            rates: 4.0,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('controlled_slowdown', $regime['key']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_a_controlled_slowdown_with_rising_unemployment_has_lower_confidence()
    {
        $macro = $this->macro(
            gdp: 1.5, gdpTrend: 'worsening',
            inflation: 2.5, inflationTrend: 'improving',
            unemployment: 4.0, unemploymentTrend: 'worsening',
            rates: 4.0,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('controlled_slowdown', $regime['key']);
        // 4 of 5 characteristic signals still line up (unemployment rising
        // breaks the 5th) - confidence reflects that, not just the match.
        $this->assertSame(80, $regime['confidence_percent']);
    }

    public function test_accelerating_growth_with_falling_unemployment_is_a_recovery()
    {
        $macro = $this->macro(
            gdp: 1.8, gdpTrend: 'improving',
            inflation: 2.8, inflationTrend: 'stable',
            unemployment: 5.0, unemploymentTrend: 'improving',
            production: 0.5, productionTrend: 'improving',
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('recovery', $regime['key']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_steady_positive_growth_with_no_distinctive_signal_defaults_to_expansion()
    {
        $macro = $this->macro(
            gdp: 2.0, gdpTrend: 'neutral',
            inflation: 2.5, inflationTrend: 'stable',
            unemployment: 4.0, unemploymentTrend: 'stable',
            production: 0.8,
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertSame('expansion', $regime['key']);
        $this->assertSame(100, $regime['confidence_percent']);
    }

    public function test_missing_core_indicators_reports_insufficient_data()
    {
        $regime = $this->classifier()->classify([]);

        $this->assertSame('insufficient_data', $regime['key']);
        $this->assertSame(0, $regime['confidence_percent']);
        $this->assertSame([], $regime['signals']);
    }

    public function test_every_regime_has_a_description()
    {
        $macro = $this->macro(
            gdp: 2.0, gdpTrend: 'neutral',
            inflation: 2.5, inflationTrend: 'stable',
            unemployment: 4.0, unemploymentTrend: 'stable',
        );

        $regime = $this->classifier()->classify($macro);

        $this->assertNotEmpty($regime['description']);
    }
}
