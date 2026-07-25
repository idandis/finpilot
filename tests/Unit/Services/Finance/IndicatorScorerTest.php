<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\IndicatorScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndicatorScorerTest extends TestCase
{
    public function test_it_returns_null_when_the_value_is_null()
    {
        $this->assertNull(IndicatorScorer::score('revenue_growth', null));
    }

    public function test_it_returns_null_for_an_unscored_indicator()
    {
        $this->assertNull(IndicatorScorer::score('free_cash_flow', 1000000.0));
    }

    #[DataProvider('higherIsBetterProvider')]
    public function test_higher_is_better_indicators($indicator, $value, $expected)
    {
        $this->assertSame($expected, IndicatorScorer::score($indicator, $value));
    }

    public static function higherIsBetterProvider(): array
    {
        return [
            'revenue_growth negative' => ['revenue_growth', -5.0, 2],
            'revenue_growth weak' => ['revenue_growth', 2.0, 4],
            'revenue_growth good' => ['revenue_growth', 10.0, 7],
            'revenue_growth excellent' => ['revenue_growth', 20.0, 10],
            'eps_growth excellent' => ['eps_growth', 25.0, 10],
            'operating_margin low' => ['operating_margin', 5.0, 3],
            'operating_margin excellent' => ['operating_margin', 35.0, 10],
            'roe weak' => ['roe', 8.0, 3],
            'roe excellent' => ['roe', 25.0, 10],
            'roic weak' => ['roic', 3.0, 2],
            'roic excellent' => ['roic', 18.0, 10],
            'fcf_yield low' => ['fcf_yield', 1.0, 3],
            'fcf_yield normal' => ['fcf_yield', 4.0, 6],
            'fcf_yield interesting' => ['fcf_yield', 7.0, 9],
            'revenue_cagr_5y negative' => ['revenue_cagr_5y', -2.0, 2],
            'revenue_cagr_5y excellent' => ['revenue_cagr_5y', 18.0, 10],
            'eps_cagr_5y weak' => ['eps_cagr_5y', 3.0, 4],
            'eps_cagr_5y excellent' => ['eps_cagr_5y', 25.0, 10],
            'net_margin low' => ['net_margin', 3.0, 2],
            'net_margin excellent' => ['net_margin', 25.0, 10],
            'gross_margin low' => ['gross_margin', 15.0, 2],
            'gross_margin excellent' => ['gross_margin', 70.0, 10],
            'fcf_margin low' => ['fcf_margin', 2.0, 2],
            'fcf_margin excellent' => ['fcf_margin', 25.0, 10],
            'interest_coverage risky' => ['interest_coverage', 1.0, 2],
            'interest_coverage excellent' => ['interest_coverage', 15.0, 10],
            'current_ratio risky' => ['current_ratio', 0.8, 2],
            'current_ratio excellent' => ['current_ratio', 2.5, 10],
        ];
    }

    #[DataProvider('lowerIsBetterProvider')]
    public function test_lower_is_better_indicators($indicator, $value, $expected)
    {
        $this->assertSame($expected, IndicatorScorer::score($indicator, $value));
    }

    public static function lowerIsBetterProvider(): array
    {
        return [
            'debt_to_ebitda very solid' => ['debt_to_ebitda', 0.5, 10],
            'debt_to_ebitda risky' => ['debt_to_ebitda', 5.0, 2],
            'pe_ratio cheap' => ['pe_ratio', 10.0, 9],
            'pe_ratio very expensive' => ['pe_ratio', 60.0, 2],
            'ev_to_ebitda cheap' => ['ev_to_ebitda', 8.0, 9],
            'ev_to_ebitda elevated' => ['ev_to_ebitda', 25.0, 3],
            'peg_ratio interesting' => ['peg_ratio', 0.8, 9],
            'peg_ratio demanding' => ['peg_ratio', 2.5, 3],
            'ev_to_fcf cheap' => ['ev_to_fcf', 10.0, 9],
            'ev_to_fcf very expensive' => ['ev_to_fcf', 60.0, 2],
            'price_to_sales cheap' => ['price_to_sales', 0.5, 9],
            'price_to_sales very expensive' => ['price_to_sales', 15.0, 2],
        ];
    }
}
