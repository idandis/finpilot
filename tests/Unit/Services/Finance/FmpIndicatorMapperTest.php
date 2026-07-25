<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\FmpIndicatorMapper;
use Tests\TestCase;

class FmpIndicatorMapperTest extends TestCase
{
    private function fundamentals(): array
    {
        return [
            'profile' => ['currency' => 'USD', 'price' => 100.0, 'marketCap' => 3000000000000],
            'ratios' => [
                'priceToEarningsRatioTTM' => 34.2,
                'priceToEarningsGrowthRatioTTM' => 1.9,
                'operatingProfitMarginTTM' => 0.30,
                'enterpriseValueMultipleTTM' => 21.3,
                'netProfitMarginTTM' => 0.25,
                'grossProfitMarginTTM' => 0.45,
                'interestCoverageRatioTTM' => 12.4,
                'currentRatioTTM' => 1.8,
                'priceToSalesRatioTTM' => 8.6,
                'revenuePerShareTTM' => 10.0,
            ],
            'keyMetrics' => [
                'returnOnEquityTTM' => 0.147,
                'returnOnInvestedCapitalTTM' => 0.285,
                'netDebtToEBITDATTM' => 0.81,
                'evToEBITDATTM' => 21.3,
                'evToFreeCashFlowTTM' => 27.5,
                'freeCashFlowYieldTTM' => 0.05,
            ],
            'growth' => [
                'revenueGrowth' => 0.157,
                'epsgrowth' => 0.22,
            ],
            'cashFlow' => [
                'freeCashFlow' => 60000000000,
            ],
            'incomeStatementAnnual' => [
                ['revenue' => 400000000000, 'eps' => 7.49],
                ['revenue' => 391000000000, 'eps' => 6.11],
                ['revenue' => 383000000000, 'eps' => 6.16],
                ['revenue' => 394000000000, 'eps' => 6.15],
                ['revenue' => 366000000000, 'eps' => 5.67],
            ],
        ];
    }

    public function test_it_maps_all_indicators()
    {
        $result = FmpIndicatorMapper::map($this->fundamentals());

        $this->assertSame(15.7, $result['revenue_growth']);
        $this->assertSame(22.0, $result['eps_growth']);
        $this->assertSame(60000000000.0, $result['free_cash_flow']);
        $this->assertSame(30.0, $result['operating_margin']);
        $this->assertSame(25.0, $result['net_margin']);
        $this->assertSame(45.0, $result['gross_margin']);
        $this->assertSame(14.7, $result['roe']);
        $this->assertSame(28.5, $result['roic']);
        $this->assertSame(0.81, $result['debt_to_ebitda']);
        $this->assertSame(12.4, $result['interest_coverage']);
        $this->assertSame(1.8, $result['current_ratio']);
        $this->assertSame(34.2, $result['pe_ratio']);
        $this->assertSame(21.3, $result['ev_to_ebitda']);
        $this->assertSame(27.5, $result['ev_to_fcf']);
        $this->assertSame(8.6, $result['price_to_sales']);
        $this->assertSame(1.9, $result['peg_ratio']);
        $this->assertSame(5.0, $result['fcf_yield']);
        $this->assertSame('USD', $result['indicators_currency']);
        $this->assertSame(100.0, $result['current_price']);
        $this->assertSame(3000000000000.0, $result['market_cap']);

        // revenue = revenuePerShareTTM(10) * shares(marketCap/price = 30bn) = 300bn
        // fcf_margin = freeCashFlow(60bn) / revenue(300bn) * 100
        $this->assertSame(20.0, $result['fcf_margin']);

        // 4-year CAGR (5 annual statements) between the oldest and latest revenue/eps
        $this->assertEqualsWithDelta((((400000000000 / 366000000000) ** (1 / 4)) - 1) * 100, $result['revenue_cagr_5y'], 0.01);
        $this->assertEqualsWithDelta((((7.49 / 5.67) ** (1 / 4)) - 1) * 100, $result['eps_cagr_5y'], 0.01);
    }

    public function test_it_returns_null_cagr_with_fewer_than_two_years_of_statements()
    {
        $fundamentals = $this->fundamentals();
        $fundamentals['incomeStatementAnnual'] = [['revenue' => 400000000000, 'eps' => 7.49]];

        $result = FmpIndicatorMapper::map($fundamentals);

        $this->assertNull($result['revenue_cagr_5y']);
        $this->assertNull($result['eps_cagr_5y']);
    }

    public function test_it_returns_null_cagr_when_the_oldest_eps_is_not_positive()
    {
        $fundamentals = $this->fundamentals();
        $fundamentals['incomeStatementAnnual'][4]['eps'] = -1.2;

        $result = FmpIndicatorMapper::map($fundamentals);

        $this->assertNull($result['eps_cagr_5y']);
        $this->assertNotNull($result['revenue_cagr_5y']);
    }

    public function test_it_returns_null_fcf_margin_when_revenue_per_share_is_missing()
    {
        $fundamentals = $this->fundamentals();
        unset($fundamentals['ratios']['revenuePerShareTTM']);

        $result = FmpIndicatorMapper::map($fundamentals);

        $this->assertNull($result['fcf_margin']);
    }

    public function test_it_falls_back_to_ratios_ev_multiple_when_key_metrics_ev_to_ebitda_is_missing()
    {
        $fundamentals = $this->fundamentals();
        unset($fundamentals['keyMetrics']['evToEBITDATTM']);
        $fundamentals['ratios']['enterpriseValueMultipleTTM'] = 19.7;

        $result = FmpIndicatorMapper::map($fundamentals);

        $this->assertSame(19.7, $result['ev_to_ebitda']);
    }

    public function test_it_returns_all_nulls_when_the_payload_is_empty()
    {
        $result = FmpIndicatorMapper::map([]);

        foreach ([
            'current_price', 'market_cap', 'revenue_growth', 'eps_growth', 'revenue_cagr_5y', 'eps_cagr_5y',
            'free_cash_flow', 'fcf_margin', 'operating_margin', 'net_margin', 'gross_margin', 'roe', 'roic',
            'debt_to_ebitda', 'interest_coverage', 'current_ratio', 'pe_ratio', 'ev_to_ebitda', 'ev_to_fcf',
            'price_to_sales', 'peg_ratio', 'fcf_yield', 'indicators_currency',
        ] as $key) {
            $this->assertNull($result[$key], "Expected {$key} to be null");
        }
    }
}
