<?php

namespace Tests\Feature\Finance;

use App\Models\MarketOverviewPriceHistory;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\EodhdMarketPriceProvider;
use App\Services\Finance\MarketOverviewInstruments;
use App\Services\Finance\MarketOverviewRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketOverviewRefreshServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $apiKey = 'fake-token'): MarketOverviewRefreshService
    {
        return new MarketOverviewRefreshService(
            new EodhdMarketPriceProvider($apiKey, new EodhdCallBudget(dailyLimit: 1000)),
        );
    }

    public function test_it_upserts_price_history_for_every_instrument()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-29', 'open' => 5200.0, 'high' => 5260.0, 'low' => 5190.0, 'close' => 5250.5],
                ['date' => '2026-07-30', 'open' => 5250.5, 'high' => 5300.0, 'low' => 5240.0, 'close' => 5280.25],
            ]),
        ]);

        $result = $this->service()->refresh();

        $this->assertSame(count(MarketOverviewInstruments::ALL), $result->instrumentsRefreshed);
        $this->assertSame([], $result->failedInstruments);

        $sp500 = MarketOverviewPriceHistory::where('instrument_key', 'sp500')
            ->orderByDesc('price_date')
            ->first();

        $this->assertNotNull($sp500);
        $this->assertSame('2026-07-30', $sp500->price_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(5280.25, (float) $sp500->close_price, 0.0001);
        $this->assertEqualsWithDelta(5300.0, (float) $sp500->high_price, 0.0001);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'eod/GSPC.INDX'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'eod/BTC-USD.CC'));
    }

    public function test_the_first_run_backfills_a_bounded_window_not_the_full_history()
    {
        Http::fake(['eodhd.com/api/eod/*' => Http::response([])]);

        $this->service()->refresh();

        Http::assertSent(function ($request) {
            $from = Carbon::parse($request['from']);

            // Bounded to ~2 years back, not an unbounded/full-history fetch
            // (see the MySQL prepared-statement-placeholder bug this
            // exact pattern caused for MacroIndicatorRefreshService).
            return $from->gt(now()->subYears(3)) && $from->lt(now()->subMonths(18));
        });
    }

    public function test_running_refresh_twice_does_not_create_duplicate_rows()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-30', 'close' => 5280.25],
            ]),
        ]);

        $this->service()->refresh();
        $this->service()->refresh();

        $this->assertSame(1, MarketOverviewPriceHistory::where('instrument_key', 'sp500')->count());
    }

    public function test_it_records_failed_instruments_when_requests_fail()
    {
        Http::fake(['eodhd.com/api/eod/*' => Http::response(null, 500)]);

        $result = $this->service()->refresh();

        $this->assertCount(count(MarketOverviewInstruments::ALL), $result->failedInstruments);
        $this->assertSame(0, MarketOverviewPriceHistory::count());
    }
}
