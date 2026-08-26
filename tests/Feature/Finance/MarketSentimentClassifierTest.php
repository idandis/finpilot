<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\MarketSentimentClassifier;
use Tests\TestCase;

class MarketSentimentClassifierTest extends TestCase
{
    private const SECTOR_KEYS = [
        'sector_technology', 'sector_financials', 'sector_healthcare',
        'sector_consumer_discretionary', 'sector_consumer_staples', 'sector_energy',
        'sector_industrials', 'sector_materials', 'sector_utilities',
        'sector_real_estate', 'sector_communications',
    ];

    private const INDEX_KEYS = ['sp500', 'nasdaq', 'euro_stoxx_50', 'ftse_mib', 'emerging_markets'];

    private function classifier(): MarketSentimentClassifier
    {
        return new MarketSentimentClassifier;
    }

    /**
     * Builds a full market_instruments fixture: every sector trending the
     * given direction, every index+sector at the given distance from its
     * high, and the given VIX level - the same 16 "breadth" instruments
     * (5 indices + 11 sectors) the classifier itself reads.
     *
     * @return array<string, array<string, mixed>>
     */
    private function markets(?float $vix, string $sectorTrend, float $distanceFromHigh): array
    {
        $instruments = [
            'vix' => ['current' => $vix !== null ? ['value' => $vix] : null],
        ];

        foreach (self::SECTOR_KEYS as $key) {
            $instruments[$key] = [
                'current' => ['value' => 100.0],
                'trend' => $sectorTrend,
                'distance_from_high_percent' => $distanceFromHigh,
            ];
        }

        foreach (self::INDEX_KEYS as $key) {
            $instruments[$key] = [
                'current' => ['value' => 100.0],
                'distance_from_high_percent' => $distanceFromHigh,
            ];
        }

        return $instruments;
    }

    public function test_low_vix_positive_breadth_and_proximity_to_highs_is_optimism()
    {
        $result = $this->classifier()->classify($this->markets(vix: 12.0, sectorTrend: 'up', distanceFromHigh: -1.0));

        $this->assertSame('Ottimismo', $result['sentiment']);
        $this->assertSame(3, $result['score']);
        $this->assertCount(3, $result['positive_signals']);
        $this->assertSame([], $result['caution_signals']);
    }

    public function test_high_vix_negative_breadth_and_distance_from_highs_is_pessimism()
    {
        $result = $this->classifier()->classify($this->markets(vix: 30.0, sectorTrend: 'down', distanceFromHigh: -20.0));

        $this->assertSame('Pessimismo', $result['sentiment']);
        $this->assertSame(-3, $result['score']);
        $this->assertSame([], $result['positive_signals']);
        $this->assertCount(3, $result['caution_signals']);
    }

    public function test_a_normal_vix_range_contributes_no_signal()
    {
        $result = $this->classifier()->classify($this->markets(vix: 20.0, sectorTrend: 'neutral', distanceFromHigh: -3.0));

        // VIX in [15,25] -> no signal; sector trend 'neutral' (not up/down)
        // -> 0/11 up counts as weak breadth; -3% is within the 5% near-high
        // band -> positive proximity signal. Net: 1 caution, 1 positive.
        $this->assertSame('Neutrale', $result['sentiment']);
        $this->assertSame(0, $result['score']);
    }

    public function test_no_data_at_all_reports_no_signals_rather_than_false_pessimism()
    {
        $result = $this->classifier()->classify([]);

        // An empty/unseeded watchlist must not read as "0 sectors up out of
        // 11" (a confidently bearish signal) - it should read as no data.
        $this->assertSame('Neutrale', $result['sentiment']);
        $this->assertSame(0, $result['score']);
        $this->assertSame([], $result['positive_signals']);
        $this->assertSame([], $result['caution_signals']);
    }

    public function test_it_always_lists_the_signals_it_could_not_compute()
    {
        $result = $this->classifier()->classify([]);

        $this->assertContains('Put/call ratio (nessuna fonte gratuita disponibile)', $result['omitted_signals']);
        $this->assertContains('Flussi verso ETF (nessuna fonte gratuita disponibile)', $result['omitted_signals']);
        $this->assertContains('Fear & Greed Index (proprietario CNN, nessuna API pubblica)', $result['omitted_signals']);
    }

    public function test_moderate_breadth_in_the_ambiguous_middle_contributes_no_breadth_signal()
    {
        // 5 of 11 sectors up (~45%) sits strictly between the weak (35%)
        // and strong (65%) ratios - genuinely ambiguous, not a signal.
        $instruments = $this->markets(vix: null, sectorTrend: 'down', distanceFromHigh: -3.0);

        foreach (array_slice(self::SECTOR_KEYS, 0, 5) as $key) {
            $instruments[$key]['trend'] = 'up';
        }

        $result = $this->classifier()->classify($instruments);

        // Sector breadth: ambiguous (no signal). Proximity to highs: -3% is
        // within 5% for all 16 -> positive signal. Net: 1 positive only.
        $this->assertSame('Moderato ottimismo', $result['sentiment']);
        $this->assertSame(1, $result['score']);
        $this->assertSame(['Mercato vicino ai massimi (16/16 entro il 5% dal massimo a 52 settimane)'], $result['positive_signals']);
    }
}
