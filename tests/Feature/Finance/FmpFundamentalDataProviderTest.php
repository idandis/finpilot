<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FmpFundamentalDataProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FmpFundamentalDataProviderTest extends TestCase
{
    private function provider(): FmpFundamentalDataProvider
    {
        return new FmpFundamentalDataProvider('fake-key');
    }

    public function test_it_returns_null_without_an_api_key()
    {
        Http::fake();

        $provider = new FmpFundamentalDataProvider(null);

        $this->assertNull($provider->fetchFundamentals('AAPL.US'));
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_the_profile_call_fails()
    {
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response(null, 500),
        ]);

        $this->assertNull($this->provider()->fetchFundamentals('AAPL.US'));
    }

    public function test_it_returns_null_when_the_symbol_is_not_found()
    {
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([]),
        ]);

        $this->assertNull($this->provider()->fetchFundamentals('NOTATICKER.US'));
    }

    public function test_it_merges_all_sections_when_every_call_succeeds()
    {
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([['currency' => 'USD']]),
            'financialmodelingprep.com/stable/ratios-ttm*' => Http::response([['priceToEarningsRatioTTM' => 34.2]]),
            'financialmodelingprep.com/stable/key-metrics-ttm*' => Http::response([['returnOnInvestedCapitalTTM' => 0.28]]),
            'financialmodelingprep.com/stable/financial-growth*' => Http::response([['revenueGrowth' => 0.157]]),
            'financialmodelingprep.com/stable/cash-flow-statement*' => Http::response([['freeCashFlow' => 100000000000]]),
            'financialmodelingprep.com/stable/income-statement*' => Http::response([
                ['revenue' => 400000000000, 'eps' => 7.0],
                ['revenue' => 350000000000, 'eps' => 6.0],
            ]),
        ]);

        $result = $this->provider()->fetchFundamentals('AAPL.US');

        $this->assertSame('USD', $result['profile']['currency']);
        $this->assertSame(34.2, $result['ratios']['priceToEarningsRatioTTM']);
        $this->assertSame(0.28, $result['keyMetrics']['returnOnInvestedCapitalTTM']);
        $this->assertSame(0.157, $result['growth']['revenueGrowth']);
        $this->assertSame(100000000000, $result['cashFlow']['freeCashFlow']);
        $this->assertCount(2, $result['incomeStatementAnnual']);
        $this->assertSame(400000000000, $result['incomeStatementAnnual'][0]['revenue']);
    }

    public function test_it_requests_five_years_of_annual_income_statements()
    {
        Http::fake([
            'financialmodelingprep.com/stable/*' => Http::response([['ok' => true]]),
        ]);

        $this->provider()->fetchFundamentals('AAPL.US');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/income-statement?')
            && str_contains($request->url(), 'period=annual')
            && str_contains($request->url(), 'limit=5'));
    }

    public function test_income_statement_annual_degrades_to_an_empty_array_when_the_call_fails()
    {
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([['currency' => 'USD']]),
            'financialmodelingprep.com/stable/income-statement*' => Http::response(null, 500),
        ]);

        $result = $this->provider()->fetchFundamentals('AAPL.US');

        $this->assertSame([], $result['incomeStatementAnnual']);
    }

    public function test_individual_failed_sections_degrade_to_an_empty_array_instead_of_failing_the_whole_fetch()
    {
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([['currency' => 'USD']]),
            'financialmodelingprep.com/stable/ratios-ttm*' => Http::response(null, 500),
            'financialmodelingprep.com/stable/key-metrics-ttm*' => Http::response([]),
            'financialmodelingprep.com/stable/financial-growth*' => Http::response([['revenueGrowth' => 0.157]]),
            'financialmodelingprep.com/stable/cash-flow-statement*' => Http::response([['freeCashFlow' => 100000000000]]),
        ]);

        $result = $this->provider()->fetchFundamentals('AAPL.US');

        $this->assertNotNull($result);
        $this->assertSame([], $result['ratios']);
        $this->assertSame([], $result['keyMetrics']);
        $this->assertSame(0.157, $result['growth']['revenueGrowth']);
    }

    public function test_it_strips_a_trailing_dot_us_suffix_before_querying_fmp()
    {
        Http::fake([
            'financialmodelingprep.com/stable/*' => Http::response([['ok' => true]]),
        ]);

        $this->provider()->fetchFundamentals('AAPL.US');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/profile?symbol=AAPL&') && ! str_contains($request->url(), 'symbol=AAPL.US'));
    }

    public function test_it_leaves_a_non_us_suffix_unchanged()
    {
        Http::fake([
            'financialmodelingprep.com/stable/*' => Http::response([['ok' => true]]),
        ]);

        $this->provider()->fetchFundamentals('STLA.MI');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'symbol=STLA.MI'));
    }
}
