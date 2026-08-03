<?php

namespace App\Services\Finance;

use App\Models\CompanyAnalysis;

/**
 * Shapes a CompanyAnalysis into the plain-JSON-friendly array the frontend
 * expects (decimal casts turned into real floats, plus derived scores and
 * valuation verdict) - shared between the standalone CompanyAnalyses pages
 * and the Fundamentals tab of an Investment's Decision Journal, so both
 * render from the exact same numbers.
 */
class CompanyAnalysisPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(CompanyAnalysis $analysis): array
    {
        $indicators = [
            'revenue_growth' => self::toFloat($analysis->revenue_growth),
            'eps_growth' => self::toFloat($analysis->eps_growth),
            'revenue_cagr_5y' => self::toFloat($analysis->revenue_cagr_5y),
            'eps_cagr_5y' => self::toFloat($analysis->eps_cagr_5y),
            'operating_margin' => self::toFloat($analysis->operating_margin),
            'net_margin' => self::toFloat($analysis->net_margin),
            'gross_margin' => self::toFloat($analysis->gross_margin),
            'roe' => self::toFloat($analysis->roe),
            'roic' => self::toFloat($analysis->roic),
            'debt_to_ebitda' => self::toFloat($analysis->debt_to_ebitda),
            'interest_coverage' => self::toFloat($analysis->interest_coverage),
            'current_ratio' => self::toFloat($analysis->current_ratio),
            'pe_ratio' => self::toFloat($analysis->pe_ratio),
            'ev_to_ebitda' => self::toFloat($analysis->ev_to_ebitda),
            'ev_to_fcf' => self::toFloat($analysis->ev_to_fcf),
            'price_to_sales' => self::toFloat($analysis->price_to_sales),
            'peg_ratio' => self::toFloat($analysis->peg_ratio),
            'fcf_yield' => self::toFloat($analysis->fcf_yield),
            'fcf_margin' => self::toFloat($analysis->fcf_margin),
        ];

        $currentPrice = self::toFloat($analysis->current_price);
        $fairValue = self::toFloat($analysis->fair_value);

        return [
            'id' => $analysis->id,
            'name' => $analysis->name,
            'symbol' => $analysis->symbol,
            'current_price' => $currentPrice,
            'market_cap' => self::toFloat($analysis->market_cap),
            ...$indicators,
            'free_cash_flow' => self::toFloat($analysis->free_cash_flow),
            'fair_value' => $fairValue,
            'historical_comparison' => $analysis->historical_comparison,
            'competitor_comparison' => $analysis->competitor_comparison,
            'indicators_currency' => $analysis->indicators_currency,
            'indicators_fetched_at' => $analysis->indicators_fetched_at?->toIso8601String(),
            'price_history_fetched_at' => $analysis->price_history_fetched_at?->toIso8601String(),
            'created_at' => $analysis->created_at?->toIso8601String(),
            'updated_at' => $analysis->updated_at?->toIso8601String(),
            'scores' => collect($indicators)
                ->map(fn (?float $value, string $indicator) => IndicatorScorer::score($indicator, $value))
                ->all(),
            'valuation' => ValuationAssessor::assess($currentPrice, $fairValue),
        ];
    }

    private static function toFloat(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
