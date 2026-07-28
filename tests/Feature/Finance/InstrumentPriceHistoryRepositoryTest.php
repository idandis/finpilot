<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\InstrumentPrice;
use App\Models\InstrumentPriceHistory;
use App\Services\Finance\InstrumentPriceHistoryRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstrumentPriceHistoryRepositoryTest extends TestCase
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
        $record = InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeInstrumentHistoryProvider(history: [
            new FetchedPrice(price: 100.0, date: Carbon::parse('2026-07-20')),
            new FetchedPrice(price: 102.5, date: Carbon::parse('2026-07-21')),
        ]);

        $repository = new InstrumentPriceHistoryRepository($provider);
        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(1, $callsUsed);
        $this->assertSame(1, $provider->historyCalls);

        $stored = InstrumentPriceHistory::query()->where('isin', 'IE00BK5BQT80')->orderBy('price_date')->get();
        $this->assertCount(2, $stored);
        $this->assertSame('2026-07-20', $stored[0]->price_date->format('Y-m-d'));
        $this->assertEquals(100.0, $stored[0]->close_price);
        $this->assertSame('2026-07-21', $stored[1]->price_date->format('Y-m-d'));
        $this->assertEquals(102.5, $stored[1]->close_price);
        $this->assertNotNull($record->fresh()->history_backfilled_at);
    }

    public function test_backfill_stores_ohlc_alongside_the_close_for_the_candlestick_chart()
    {
        $record = InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeInstrumentHistoryProvider(history: [
            new FetchedPrice(price: 105.32, date: Carbon::parse('2026-07-22'), open: 103.0, high: 106.0, low: 102.5),
        ]);

        $repository = new InstrumentPriceHistoryRepository($provider);
        $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $stored = InstrumentPriceHistory::query()->where('isin', 'IE00BK5BQT80')->first();
        $this->assertEquals(103.0, $stored->open_price);
        $this->assertEquals(106.0, $stored->high_price);
        $this->assertEquals(102.5, $stored->low_price);
        $this->assertEquals(105.32, $stored->close_price);
    }

    public function test_backfill_updates_existing_rows_for_the_same_date_instead_of_colliding()
    {
        // Simulates re-running backfill (e.g. after resetting the flag to
        // pick up a newly-added column) when rows for these dates already
        // exist from a previous run - a regression test for a real bug
        // where a raw string date match missed the already-stored row
        // (SQLite persists the date-cast column with a time suffix) and
        // re-insertion collided with the unique index instead of updating.
        InstrumentPriceHistory::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'price_date' => '2026-07-20',
            'close_price' => 100.0,
            'open_price' => null,
        ]);

        $record = InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeInstrumentHistoryProvider(history: [
            new FetchedPrice(price: 105.0, date: Carbon::parse('2026-07-20'), open: 101.0, high: 106.0, low: 99.5),
        ]);

        $repository = new InstrumentPriceHistoryRepository($provider);
        $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertDatabaseCount('instrument_price_history', 1);
        $stored = InstrumentPriceHistory::query()->where('isin', 'IE00BK5BQT80')->first();
        $this->assertEquals(105.0, $stored->close_price);
        $this->assertEquals(101.0, $stored->open_price);
    }

    public function test_it_does_nothing_when_already_backfilled()
    {
        $record = InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'history_backfilled_at' => now(),
        ]);

        $provider = new FakeInstrumentHistoryProvider;
        $repository = new InstrumentPriceHistoryRepository($provider);

        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->historyCalls);
    }

    public function test_it_does_nothing_when_the_symbol_is_not_resolved()
    {
        $record = InstrumentPrice::factory()->create([
            'isin' => 'XX0000000000',
            'code' => null,
            'exchange' => null,
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeInstrumentHistoryProvider;
        $repository = new InstrumentPriceHistoryRepository($provider);

        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->historyCalls);
    }

    public function test_a_failed_history_call_does_not_mark_the_flag_and_remains_retryable()
    {
        $record = InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'history_backfilled_at' => null,
        ]);

        $provider = new FakeInstrumentHistoryProvider(history: null);
        $repository = new InstrumentPriceHistoryRepository($provider);

        $callsUsed = $repository->backfill($record, $this->historyFrom(), $this->historyTo());

        $this->assertSame(1, $callsUsed);
        $this->assertNull($record->fresh()->history_backfilled_at);
        $this->assertDatabaseCount('instrument_price_history', 0);
    }

    public function test_history_for_orders_results_by_date()
    {
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-07-21', 'close_price' => 102.5]);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-07-20', 'close_price' => 100.0]);

        $repository = new InstrumentPriceHistoryRepository(new FakeInstrumentHistoryProvider);
        $history = $repository->historyFor('IE00BK5BQT80');

        $this->assertCount(2, $history);
        $this->assertSame('2026-07-20', $history->first()->price_date->format('Y-m-d'));
        $this->assertSame('2026-07-21', $history->last()->price_date->format('Y-m-d'));
    }
}

class FakeInstrumentHistoryProvider implements MarketPriceProvider
{
    public int $historyCalls = 0;

    /** @param array<int, FetchedPrice>|null $history */
    public function __construct(private readonly ?array $history = []) {}

    public function resolveSymbol(string $isin): ?ResolvedSymbol
    {
        return null;
    }

    public function fetchPrice(string $code, string $exchange): ?FetchedPrice
    {
        return null;
    }

    public function fetchRealtimePrice(string $code, string $exchange): ?FetchedPrice
    {
        return null;
    }

    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array
    {
        $this->historyCalls++;

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
