<?php

namespace Tests\Feature\Finance;

use App\Models\MarketOverviewPriceHistory;
use App\Services\Finance\MarketOverviewPerformanceCalculator;
use App\Services\Finance\MarketOverviewRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketOverviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): MarketOverviewRepository
    {
        return new MarketOverviewRepository(new MarketOverviewPerformanceCalculator);
    }

    /**
     * Seeds a sector instrument's history so its quarter/week change signs
     * are controlled precisely: an older anchor sets the quarter baseline,
     * a near anchor sets the week baseline, and "today" is the current
     * price - matching the same well-spaced-anchor technique used in
     * MarketOverviewPerformanceCalculatorTest to avoid ambiguity about
     * which observation each window picks up.
     */
    private function seedSector(string $key, float $quarterBaseline, float $weekBaseline, float $current): void
    {
        MarketOverviewPriceHistory::create(['instrument_key' => $key, 'price_date' => '2026-04-15', 'close_price' => $quarterBaseline]);
        MarketOverviewPriceHistory::create(['instrument_key' => $key, 'price_date' => '2026-07-20', 'close_price' => $weekBaseline]);
        MarketOverviewPriceHistory::create(['instrument_key' => $key, 'price_date' => '2026-07-30', 'close_price' => $current]);
    }

    private function findInstrument(array $categories, string $key): ?array
    {
        foreach ($categories as $category) {
            foreach ($category['instruments'] as $instrument) {
                if ($instrument['key'] === $key) {
                    return $instrument;
                }
            }
        }

        return null;
    }

    public function test_a_sector_strong_and_still_climbing_is_accelerating()
    {
        // Quarter change and week change both positive.
        $this->seedSector('sector_technology', quarterBaseline: 100.0, weekBaseline: 105.0, current: 110.0);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sector_technology');

        $this->assertSame('strong_accelerating', $instrument['rotation']);
    }

    public function test_a_sector_strong_but_fading_is_slowing()
    {
        // Quarter change positive, week change negative.
        $this->seedSector('sector_technology', quarterBaseline: 100.0, weekBaseline: 115.0, current: 110.0);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sector_technology');

        $this->assertSame('strong_slowing', $instrument['rotation']);
    }

    public function test_a_sector_weak_but_bouncing_is_recovering()
    {
        // Quarter change negative, week change positive.
        $this->seedSector('sector_energy', quarterBaseline: 120.0, weekBaseline: 105.0, current: 110.0);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sector_energy');

        $this->assertSame('weak_recovering', $instrument['rotation']);
    }

    public function test_a_sector_weak_and_still_falling_is_worsening()
    {
        // Quarter change negative, week change negative.
        $this->seedSector('sector_energy', quarterBaseline: 120.0, weekBaseline: 115.0, current: 110.0);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sector_energy');

        $this->assertSame('weak_worsening', $instrument['rotation']);
    }

    public function test_rotation_is_null_for_non_sector_instruments()
    {
        MarketOverviewPriceHistory::create(['instrument_key' => 'sp500', 'price_date' => '2026-07-29', 'close_price' => 100.0]);
        MarketOverviewPriceHistory::create(['instrument_key' => 'sp500', 'price_date' => '2026-07-30', 'close_price' => 105.0]);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sp500');

        $this->assertNull($instrument['rotation']);
    }

    public function test_rotation_is_null_without_enough_history_for_the_quarter_window()
    {
        MarketOverviewPriceHistory::create(['instrument_key' => 'sector_technology', 'price_date' => '2026-07-30', 'close_price' => 100.0]);

        $instrument = $this->findInstrument($this->repository()->categorized(), 'sector_technology');

        $this->assertNull($instrument['rotation']);
    }

    public function test_all_eleven_spdr_sectors_appear_in_the_settori_category()
    {
        MarketOverviewPriceHistory::create(['instrument_key' => 'sector_technology', 'price_date' => '2026-07-30', 'close_price' => 100.0]);

        $categories = $this->repository()->categorized();
        $sectorCategory = collect($categories)->firstWhere('key', 'settori');

        $this->assertNotNull($sectorCategory);
        $this->assertCount(11, $sectorCategory['instruments']);
    }
}
