<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\FundamentalIndicatorMapper;
use Tests\TestCase;

class FundamentalIndicatorMapperTest extends TestCase
{
    private function fundamentals(): array
    {
        return [
            'General' => ['CurrencyCode' => 'USD'],
            'Highlights' => [
                'QuarterlyRevenueGrowthYOY' => 0.157,
                'QuarterlyEarningsGrowthYOY' => 0.22,
                'OperatingMarginTTM' => 0.30,
                'ReturnOnEquityTTM' => 0.147,
                'EBITDA' => 130000000000,
                'PERatio' => 28.5,
                'PEGRatio' => 1.8,
                'MarketCapitalization' => 2000000000000,
            ],
            'Valuation' => [
                'TrailingPE' => 27.9,
                'EnterpriseValueEbitda' => 21.3,
            ],
            'Financials' => [
                'Income_Statement' => [
                    'yearly' => [
                        '2024-09-30' => ['ebit' => 123000000000],
                        '2025-09-30' => ['ebit' => 130000000000],
                    ],
                ],
                'Balance_Sheet' => [
                    'yearly' => [
                        '2024-09-30' => [
                            'totalStockholderEquity' => 60000000000,
                            'cash' => 25000000000,
                            'shortLongTermDebtTotal' => 100000000000,
                        ],
                        '2025-09-30' => [
                            'totalStockholderEquity' => 65000000000,
                            'cash' => 30000000000,
                            'shortLongTermDebtTotal' => 105000000000,
                        ],
                    ],
                ],
                'Cash_Flow' => [
                    'yearly' => [
                        '2024-09-30' => ['freeCashFlow' => 95000000000],
                        '2025-09-30' => ['freeCashFlow' => 100000000000],
                    ],
                ],
            ],
        ];
    }

    public function test_it_maps_all_indicators_from_the_latest_fiscal_year()
    {
        $result = FundamentalIndicatorMapper::map($this->fundamentals());

        $this->assertSame(15.7, $result['revenue_growth']);
        $this->assertSame(22.0, $result['eps_growth']);
        $this->assertSame(100000000000.0, $result['free_cash_flow']);
        $this->assertSame(30.0, $result['operating_margin']);
        $this->assertSame(14.7, $result['roe']);
        $this->assertSame(0.8077, $result['debt_to_ebitda']);
        $this->assertSame(28.5, $result['pe_ratio']);
        $this->assertSame(21.3, $result['ev_to_ebitda']);
        $this->assertSame(1.8, $result['peg_ratio']);
        // FCF Yield = 100e9 / 2000e9 * 100 = 5%
        $this->assertSame(5.0, $result['fcf_yield']);
        $this->assertSame('USD', $result['indicators_currency']);

        // NOPAT = 130e9 * 0.79 = 102.7e9; invested capital = 105e9 + 65e9 - 30e9 = 140e9
        $this->assertSame(round((102700000000 / 140000000000) * 100, 2), $result['roic']);
    }

    public function test_it_falls_back_to_valuation_pe_when_highlights_pe_is_missing()
    {
        $fundamentals = $this->fundamentals();
        unset($fundamentals['Highlights']['PERatio']);

        $result = FundamentalIndicatorMapper::map($fundamentals);

        $this->assertSame(27.9, $result['pe_ratio']);
    }

    public function test_it_derives_free_cash_flow_from_operating_cash_flow_and_capex_when_not_reported_directly()
    {
        $fundamentals = $this->fundamentals();
        unset($fundamentals['Financials']['Cash_Flow']['yearly']['2025-09-30']['freeCashFlow']);
        $fundamentals['Financials']['Cash_Flow']['yearly']['2025-09-30']['totalCashFromOperatingActivities'] = 110000000000;
        $fundamentals['Financials']['Cash_Flow']['yearly']['2025-09-30']['capitalExpenditures'] = -10000000000;

        $result = FundamentalIndicatorMapper::map($fundamentals);

        $this->assertSame(100000000000.0, $result['free_cash_flow']);
    }

    public function test_it_derives_total_debt_from_long_and_short_term_debt_when_the_total_field_is_missing()
    {
        $fundamentals = $this->fundamentals();
        unset($fundamentals['Financials']['Balance_Sheet']['yearly']['2025-09-30']['shortLongTermDebtTotal']);
        $fundamentals['Financials']['Balance_Sheet']['yearly']['2025-09-30']['longTermDebt'] = 90000000000;
        $fundamentals['Financials']['Balance_Sheet']['yearly']['2025-09-30']['shortTermDebt'] = 15000000000;

        $result = FundamentalIndicatorMapper::map($fundamentals);

        $this->assertSame(round(105000000000 / 130000000000, 4), $result['debt_to_ebitda']);
    }

    public function test_it_returns_all_nulls_when_the_payload_is_empty()
    {
        $result = FundamentalIndicatorMapper::map([]);

        $this->assertNull($result['revenue_growth']);
        $this->assertNull($result['eps_growth']);
        $this->assertNull($result['free_cash_flow']);
        $this->assertNull($result['operating_margin']);
        $this->assertNull($result['roe']);
        $this->assertNull($result['roic']);
        $this->assertNull($result['debt_to_ebitda']);
        $this->assertNull($result['pe_ratio']);
        $this->assertNull($result['ev_to_ebitda']);
        $this->assertNull($result['peg_ratio']);
        $this->assertNull($result['fcf_yield']);
        $this->assertNull($result['indicators_currency']);
    }

    public function test_it_treats_the_string_na_as_a_missing_value()
    {
        $fundamentals = $this->fundamentals();
        $fundamentals['Highlights']['PEGRatio'] = 'NA';

        $result = FundamentalIndicatorMapper::map($fundamentals);

        $this->assertNull($result['peg_ratio']);
    }
}
