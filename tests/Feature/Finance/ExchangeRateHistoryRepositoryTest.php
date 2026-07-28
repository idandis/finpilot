<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Services\Finance\ExchangeRateHistoryRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExchangeRateHistoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function historyFrom(): Carbon
    {
        return Carbon::parse('2026-01-01');
    }

    private function historyTo(): Carbon
    {
        return Carbon::parse('2026-07-23');
    }

    public function test_backfill_populates_history_and_marks_the_flag()
    {
        $record = ExchangeRate::factory()->create([
            'currency' => 'USD',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeRateHistoryProvider(history: [
            new FetchedPrice(price: 0.90, date: Carbon::parse('2026-07-20')),
            new FetchedPrice(price: 0.92, date: Carbon::parse('2026-07-21')),
        ]);

        $repository = new ExchangeRateHistoryRepository($provider);
        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(1, $callsUsed);
        $this->assertSame(1, $provider->historyCalls);
        $this->assertSame('USDEUR', $provider->lastCode);
        $this->assertSame('FOREX', $provider->lastExchange);

        $stored = ExchangeRateHistory::query()->where('currency', 'USD')->orderBy('rate_date')->get();
        $this->assertCount(2, $stored);
        $this->assertSame('2026-07-20', $stored[0]->rate_date->format('Y-m-d'));
        $this->assertEquals(0.90, $stored[0]->rate_to_eur);
        $this->assertSame('2026-07-21', $stored[1]->rate_date->format('Y-m-d'));
        $this->assertEquals(0.92, $stored[1]->rate_to_eur);
        $this->assertNotNull($record->fresh()->history_backfilled_at);
    }

    public function test_it_does_nothing_when_already_backfilled()
    {
        $record = ExchangeRate::factory()->create([
            'currency' => 'USD',
            'history_backfilled_at' => now(),
        ]);

        $provider = new FakeRateHistoryProvider;
        $repository = new ExchangeRateHistoryRepository($provider);

        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->historyCalls);
    }

    public function test_a_failed_history_call_does_not_mark_the_flag_and_remains_retryable()
    {
        $record = ExchangeRate::factory()->create([
            'currency' => 'USD',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeRateHistoryProvider(history: null);
        $repository = new ExchangeRateHistoryRepository($provider);

        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(1, $callsUsed);
        $this->assertNull($record->fresh()->history_backfilled_at);
        $this->assertDatabaseCount('exchange_rate_history', 0);
    }

    public function test_history_for_orders_results_by_date()
    {
        ExchangeRateHistory::factory()->create(['currency' => 'USD', 'rate_date' => '2026-07-21', 'rate_to_eur' => 0.92]);
        ExchangeRateHistory::factory()->create(['currency' => 'USD', 'rate_date' => '2026-07-20', 'rate_to_eur' => 0.90]);

        $repository = new ExchangeRateHistoryRepository(new FakeRateHistoryProvider);
        $history = $repository->historyFor('USD');

        $this->assertCount(2, $history);
        $this->assertSame('2026-07-20', $history->first()->rate_date->format('Y-m-d'));
        $this->assertSame('2026-07-21', $history->last()->rate_date->format('Y-m-d'));
    }
}

class FakeRateHistoryProvider implements MarketPriceProvider
{
    public int $historyCalls = 0;

    public ?string $lastCode = null;

    public ?string $lastExchange = null;

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
        $this->lastCode = $code;
        $this->lastExchange = $exchange;

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
