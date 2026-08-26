<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\RiskSentimentClassifier;
use Tests\TestCase;

class RiskSentimentClassifierTest extends TestCase
{
    private function classifier(): RiskSentimentClassifier
    {
        return new RiskSentimentClassifier;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function macro(?float $yield10y): array
    {
        return [
            'us_yield_10y' => ['current' => $yield10y !== null ? ['value' => $yield10y] : null],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function markets(
        ?float $sp500DayChange,
        ?string $vixTrend,
        ?float $eurUsdDayChange,
        ?float $goldWeekChange,
    ): array {
        return [
            'sp500' => ['day_change_percent' => $sp500DayChange],
            'vix' => ['trend' => $vixTrend],
            'eur_usd' => ['day_change_percent' => $eurUsdDayChange],
            'gold' => ['week_change_percent' => $goldWeekChange],
        ];
    }

    public function test_all_five_signals_positive_is_risk_on()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: 3.2),
            $this->markets(sp500DayChange: 1.0, vixTrend: 'down', eurUsdDayChange: 0.5, goldWeekChange: -1.0),
        );

        $this->assertSame('Risk On', $result['condition']);
        $this->assertSame(5, $result['score']);
        $this->assertCount(5, $result['positive_signals']);
        $this->assertSame([], $result['caution_signals']);
    }

    public function test_a_couple_of_mild_signals_is_moderately_risk_on()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: null),
            $this->markets(sp500DayChange: 1.0, vixTrend: 'down', eurUsdDayChange: 0.4, goldWeekChange: 0.8),
        );

        $this->assertSame('Moderatamente Risk On', $result['condition']);
        $this->assertSame(2, $result['score']);
        $this->assertSame(['Azioni in crescita', 'VIX in calo', 'Dollaro debole'], $result['positive_signals']);
        $this->assertSame(['Flussi verso beni rifugio (oro in salita)'], $result['caution_signals']);
    }

    public function test_opposing_signals_cancel_out_to_neutral()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: null),
            $this->markets(sp500DayChange: 1.0, vixTrend: 'up', eurUsdDayChange: null, goldWeekChange: null),
        );

        $this->assertSame('Neutrale', $result['condition']);
        $this->assertSame(0, $result['score']);
        $this->assertSame(['Azioni in crescita'], $result['positive_signals']);
        $this->assertSame(['VIX in aumento'], $result['caution_signals']);
    }

    public function test_two_caution_signals_is_moderately_risk_off()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: null),
            $this->markets(sp500DayChange: -0.5, vixTrend: 'up', eurUsdDayChange: null, goldWeekChange: null),
        );

        $this->assertSame('Moderatamente Risk Off', $result['condition']);
        $this->assertSame(-2, $result['score']);
    }

    public function test_all_five_signals_cautious_is_risk_off()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: 4.5),
            $this->markets(sp500DayChange: -1.0, vixTrend: 'up', eurUsdDayChange: -0.5, goldWeekChange: 1.0),
        );

        $this->assertSame('Risk Off', $result['condition']);
        $this->assertSame(-5, $result['score']);
        $this->assertSame([], $result['positive_signals']);
        $this->assertCount(5, $result['caution_signals']);
        $this->assertContains('Treasury 10Y elevato', $result['caution_signals']);
    }

    public function test_a_treasury_yield_between_the_two_thresholds_contributes_no_signal()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: 3.8),
            $this->markets(sp500DayChange: null, vixTrend: null, eurUsdDayChange: null, goldWeekChange: null),
        );

        $this->assertSame('Neutrale', $result['condition']);
        $this->assertSame(0, $result['score']);
    }

    public function test_a_flat_zero_change_contributes_no_signal()
    {
        $result = $this->classifier()->classify(
            $this->macro(yield10y: null),
            $this->markets(sp500DayChange: 0.0, vixTrend: 'neutral', eurUsdDayChange: 0.0, goldWeekChange: 0.0),
        );

        $this->assertSame('Neutrale', $result['condition']);
        $this->assertSame(0, $result['score']);
        $this->assertSame([], $result['positive_signals']);
        $this->assertSame([], $result['caution_signals']);
    }

    public function test_missing_data_everywhere_is_neutral_with_no_signals()
    {
        $result = $this->classifier()->classify([], []);

        $this->assertSame('Neutrale', $result['condition']);
        $this->assertSame(0, $result['score']);
        $this->assertSame([], $result['positive_signals']);
        $this->assertSame([], $result['caution_signals']);
    }
}
