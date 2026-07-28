<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\ExchangeRate;
use App\Services\Finance\ExchangeRateRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExchangeRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_refresh_fetches_the_rate_using_a_single_call()
    {
        $provider = new FakeRateProvider(
            price: new FetchedPrice(price: 0.92, date: Carbon::parse('2026-07-23')),
        );

        $repository = new ExchangeRateRepository($provider);
        $callsUsed = $repository->refresh('USD', callsRemaining: 10);

        $this->assertSame(1, $callsUsed);
        $this->assertSame(1, $provider->fetchCalls);
        $this->assertSame('USDEUR', $provider->lastCode);
        $this->assertSame('FOREX', $provider->lastExchange);

        $this->assertDatabaseHas('exchange_rates', [
            'currency' => 'USD',
            'rate_to_eur' => 0.92,
        ]);
    }

    public function test_a_fresh_cached_rate_is_not_refreshed()
    {
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'fetched_at' => now(),
        ]);

        $provider = new FakeRateProvider;
        $repository = new ExchangeRateRepository($provider);

        $callsUsed = $repository->refresh('USD', callsRemaining: 10);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_force_refreshes_a_fresh_cached_rate_anyway()
    {
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.90,
            'fetched_at' => now(),
        ]);

        $provider = new FakeRateProvider(
            price: new FetchedPrice(price: 0.93, date: Carbon::parse('2026-07-24')),
        );
        $repository = new ExchangeRateRepository($provider);

        $callsUsed = $repository->refresh('USD', callsRemaining: 10, force: true);

        $this->assertSame(1, $callsUsed);
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate_to_eur' => 0.93]);
    }

    public function test_a_stale_cached_rate_is_refreshed()
    {
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.90,
            'fetched_at' => now()->subHours(25),
        ]);

        $provider = new FakeRateProvider(
            price: new FetchedPrice(price: 0.93, date: Carbon::parse('2026-07-23')),
        );

        $repository = new ExchangeRateRepository($provider);
        $callsUsed = $repository->refresh('USD', callsRemaining: 10);

        $this->assertSame(1, $callsUsed);
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate_to_eur' => 0.93]);
    }

    public function test_it_does_nothing_when_no_calls_remain()
    {
        $provider = new FakeRateProvider(
            price: new FetchedPrice(price: 0.92, date: Carbon::parse('2026-07-23')),
        );

        $repository = new ExchangeRateRepository($provider);
        $callsUsed = $repository->refresh('USD', callsRemaining: 0);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate_to_eur' => null]);
    }
}

class FakeRateProvider implements MarketPriceProvider
{
    public int $fetchCalls = 0;

    public ?string $lastCode = null;

    public ?string $lastExchange = null;

    public function __construct(private readonly ?FetchedPrice $price = null) {}

    public function resolveSymbol(string $isin): ?ResolvedSymbol
    {
        return null;
    }

    public function fetchRealtimePrice(string $code, string $exchange): ?FetchedPrice
    {
        return null;
    }

    public function fetchPrice(string $code, string $exchange): ?FetchedPrice
    {
        $this->fetchCalls++;
        $this->lastCode = $code;
        $this->lastExchange = $exchange;

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
