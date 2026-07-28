<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\CompanyAnalysis;
use App\Models\CompanyAnalysisPriceHistory;
use App\Services\Finance\CompanyAnalysisPriceHistoryRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompanyAnalysisPriceHistoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function historyFrom(): Carbon
    {
        return Carbon::parse('2026-01-01');
    }

    private function historyTo(): Carbon
    {
        return Carbon::parse('2026-07-25');
    }

    public function test_refresh_populates_history_and_marks_the_timestamp()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => null]);

        $provider = new FakeCompanyAnalysisHistoryProvider(history: [
            new FetchedPrice(price: 400.0, date: Carbon::parse('2026-07-20'), open: 398.0, high: 402.0, low: 397.0),
            new FetchedPrice(price: 405.5, date: Carbon::parse('2026-07-21'), open: 400.0, high: 406.0, low: 399.0),
        ]);

        $repository = new CompanyAnalysisPriceHistoryRepository($provider);
        $result = $repository->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertTrue($result);
        $this->assertSame(1, $provider->historyCalls);

        $stored = CompanyAnalysisPriceHistory::query()->where('symbol', 'MSFT.US')->orderBy('price_date')->get();
        $this->assertCount(2, $stored);
        $this->assertSame('2026-07-20', $stored[0]->price_date->format('Y-m-d'));
        $this->assertEquals(400.0, $stored[0]->close_price);
        $this->assertEquals(398.0, $stored[0]->open_price);
        $this->assertNotNull($analysis->fresh()->price_history_fetched_at);
    }

    public function test_refresh_splits_a_bare_ticker_defaulting_the_exchange_to_us()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT', 'price_history_fetched_at' => null]);
        $provider = new FakeCompanyAnalysisHistoryProvider;

        (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertSame(['MSFT', 'US'], $provider->lastCodeExchange);
    }

    public function test_refresh_splits_an_exchange_suffixed_symbol()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'STLA.MI', 'price_history_fetched_at' => null]);
        $provider = new FakeCompanyAnalysisHistoryProvider;

        (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertSame(['STLA', 'MI'], $provider->lastCodeExchange);
    }

    public function test_refresh_updates_existing_rows_for_the_same_date_instead_of_colliding()
    {
        CompanyAnalysisPriceHistory::factory()->create([
            'symbol' => 'MSFT.US',
            'price_date' => '2026-07-20',
            'close_price' => 100.0,
            'open_price' => null,
        ]);

        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => null]);

        $provider = new FakeCompanyAnalysisHistoryProvider(history: [
            new FetchedPrice(price: 105.0, date: Carbon::parse('2026-07-20'), open: 101.0, high: 106.0, low: 99.5),
        ]);

        (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertDatabaseCount('company_analysis_price_history', 1);
        $stored = CompanyAnalysisPriceHistory::query()->where('symbol', 'MSFT.US')->first();
        $this->assertEquals(105.0, $stored->close_price);
        $this->assertEquals(101.0, $stored->open_price);
    }

    public function test_it_skips_the_call_when_already_refreshed_within_24h()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => now()->subHours(2)]);
        $provider = new FakeCompanyAnalysisHistoryProvider;

        $result = (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertTrue($result);
        $this->assertSame(0, $provider->historyCalls);
    }

    public function test_it_refreshes_again_after_24h()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => now()->subHours(25)]);
        $provider = new FakeCompanyAnalysisHistoryProvider;

        (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertSame(1, $provider->historyCalls);
    }

    public function test_a_failed_call_returns_false_and_does_not_mark_the_timestamp()
    {
        $analysis = CompanyAnalysis::factory()->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => null]);
        $provider = new FakeCompanyAnalysisHistoryProvider(history: null);

        $result = (new CompanyAnalysisPriceHistoryRepository($provider))->refresh($analysis, $this->historyFrom(), $this->historyTo());

        $this->assertFalse($result);
        $this->assertNull($analysis->fresh()->price_history_fetched_at);
        $this->assertDatabaseCount('company_analysis_price_history', 0);
    }

    public function test_history_for_orders_results_by_date()
    {
        CompanyAnalysisPriceHistory::factory()->create(['symbol' => 'MSFT.US', 'price_date' => '2026-07-21', 'close_price' => 405.5]);
        CompanyAnalysisPriceHistory::factory()->create(['symbol' => 'MSFT.US', 'price_date' => '2026-07-20', 'close_price' => 400.0]);

        $repository = new CompanyAnalysisPriceHistoryRepository(new FakeCompanyAnalysisHistoryProvider);
        $history = $repository->historyFor('MSFT.US');

        $this->assertCount(2, $history);
        $this->assertSame('2026-07-20', $history->first()->price_date->format('Y-m-d'));
        $this->assertSame('2026-07-21', $history->last()->price_date->format('Y-m-d'));
    }
}

class FakeCompanyAnalysisHistoryProvider implements MarketPriceProvider
{
    public int $historyCalls = 0;

    /** @var array{0: string, 1: string}|null */
    public ?array $lastCodeExchange = null;

    /** @param array<int, FetchedPrice>|null $history */
    public function __construct(private readonly ?array $history = []) {}

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
        return null;
    }

    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $this->historyCalls++;
        $this->lastCodeExchange = [$code, $exchange];

        return $this->history;
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
