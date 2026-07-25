<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\InstrumentPrice;
use App\Services\Finance\InstrumentPriceRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstrumentPriceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_refresh_resolves_the_symbol_and_fetches_the_price_using_two_calls()
    {
        $provider = new FakeMarketPriceProvider(
            resolved: new ResolvedSymbol(code: 'VWCE', exchange: 'XETRA', currency: 'EUR'),
            price: new FetchedPrice(price: 105.32, date: Carbon::parse('2026-07-22')),
        );

        $repository = new InstrumentPriceRepository($provider);
        $callsUsed = $repository->refresh('IE00BK5BQT80', callsRemaining: 10);

        $this->assertSame(2, $callsUsed);
        $this->assertSame(1, $provider->resolveCalls);
        $this->assertSame(1, $provider->fetchCalls);

        $this->assertDatabaseHas('instrument_prices', [
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'currency' => 'EUR',
            'last_price' => 105.32,
        ]);
    }

    public function test_a_fresh_cached_price_is_not_refreshed()
    {
        InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'fetched_at' => now(),
        ]);

        $provider = new FakeMarketPriceProvider;
        $repository = new InstrumentPriceRepository($provider);

        $callsUsed = $repository->refresh('IE00BK5BQT80', callsRemaining: 10);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_force_refreshes_a_fresh_cached_price_anyway()
    {
        InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'last_price' => 100,
            'fetched_at' => now(),
        ]);

        $provider = new FakeMarketPriceProvider(
            price: new FetchedPrice(price: 110.00, date: Carbon::parse('2026-07-24')),
        );
        $repository = new InstrumentPriceRepository($provider);

        $callsUsed = $repository->refresh('IE00BK5BQT80', callsRemaining: 10, force: true);

        $this->assertSame(1, $callsUsed);
        $this->assertSame(1, $provider->fetchCalls);
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'IE00BK5BQT80', 'last_price' => 110.00]);
    }

    public function test_a_stale_cached_price_is_refreshed_using_a_single_call()
    {
        InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'last_price' => 100,
            'fetched_at' => now()->subHours(25),
        ]);

        $provider = new FakeMarketPriceProvider(
            price: new FetchedPrice(price: 110.00, date: Carbon::parse('2026-07-23')),
        );

        $repository = new InstrumentPriceRepository($provider);
        $callsUsed = $repository->refresh('IE00BK5BQT80', callsRemaining: 10);

        $this->assertSame(1, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
        $this->assertSame(1, $provider->fetchCalls);
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'IE00BK5BQT80', 'last_price' => 110.00]);
    }

    public function test_an_isin_with_failed_resolution_is_never_retried()
    {
        InstrumentPrice::factory()->create([
            'isin' => 'XX0000000000',
            'code' => null,
            'exchange' => null,
            'resolution_failed' => true,
            'fetched_at' => null,
        ]);

        $provider = new FakeMarketPriceProvider;
        $repository = new InstrumentPriceRepository($provider);

        $callsUsed = $repository->refresh('XX0000000000', callsRemaining: 10);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
    }

    public function test_a_crypto_isin_resolves_locally_without_spending_a_search_call()
    {
        $provider = new FakeMarketPriceProvider(
            price: new FetchedPrice(price: 65000.0, date: Carbon::parse('2026-07-24')),
        );

        $repository = new InstrumentPriceRepository($provider);
        $callsUsed = $repository->refresh('XF000BTC0017', callsRemaining: 10);

        // Resolution itself cost nothing (derived from the pseudo-ISIN) -
        // only the price fetch spends a call.
        $this->assertSame(1, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
        $this->assertSame(1, $provider->fetchCalls);

        $this->assertDatabaseHas('instrument_prices', [
            'isin' => 'XF000BTC0017',
            'code' => 'BTC-USD',
            'exchange' => 'CC',
            'currency' => 'USD',
            'resolution_failed' => false,
            'last_price' => 65000.0,
        ]);
    }

    public function test_a_crypto_isin_previously_marked_resolution_failed_self_heals()
    {
        // Simulates a row created before crypto resolution existed - it was
        // marked failed after a real (doomed) search attempt.
        InstrumentPrice::factory()->create([
            'isin' => 'XF000ETH0019',
            'code' => null,
            'exchange' => null,
            'resolution_failed' => true,
            'fetched_at' => null,
        ]);

        $provider = new FakeMarketPriceProvider(
            price: new FetchedPrice(price: 3000.0, date: Carbon::parse('2026-07-24')),
        );
        $repository = new InstrumentPriceRepository($provider);

        $callsUsed = $repository->refresh('XF000ETH0019', callsRemaining: 10);

        $this->assertSame(1, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
        $this->assertDatabaseHas('instrument_prices', [
            'isin' => 'XF000ETH0019',
            'code' => 'ETH-USD',
            'exchange' => 'CC',
            'resolution_failed' => false,
            'last_price' => 3000.0,
        ]);
    }

    public function test_it_does_nothing_when_no_calls_remain()
    {
        $provider = new FakeMarketPriceProvider(
            resolved: new ResolvedSymbol(code: 'VWCE', exchange: 'XETRA', currency: 'EUR'),
        );

        $repository = new InstrumentPriceRepository($provider);
        $callsUsed = $repository->refresh('IE00BK5BQT80', callsRemaining: 0);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->resolveCalls);
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'IE00BK5BQT80', 'code' => null]);
    }
}

class FakeMarketPriceProvider implements MarketPriceProvider
{
    public int $resolveCalls = 0;

    public int $fetchCalls = 0;

    public function __construct(
        private readonly ?ResolvedSymbol $resolved = null,
        private readonly ?FetchedPrice $price = null,
    ) {}

    public function resolveSymbol(string $isin): ?ResolvedSymbol
    {
        $this->resolveCalls++;

        return $this->resolved;
    }

    public function fetchPrice(string $code, string $exchange): ?FetchedPrice
    {
        $this->fetchCalls++;

        return $this->price;
    }

    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array
    {
        return null;
    }

    public function fetchNews(string $code, string $exchange): ?array
    {
        return null;
    }

    public function fetchFundamentals(string $symbol): ?array
    {
        return null;
    }
}
