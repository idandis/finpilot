<?php

namespace App\Services\Finance;

/**
 * Turns the raw, combined FMP payload (profile + ratios-ttm + key-metrics-ttm
 * + financial-growth + cash-flow-statement) into the same 11-key indicator
 * shape FundamentalIndicatorMapper produces for EODHD, so
 * CompanyAnalysisController::refreshIndicators() doesn't care which provider
 * supplied the data. Every lookup is null-safe: a missing section (thinner
 * coverage on some exchanges, a field FMP renamed) simply yields a null
 * indicator rather than an error.
 */
class FmpIndicatorMapper
{
    /**
     * @param  array<string, mixed>  $fundamentals
     * @return array<string, mixed>
     */
    public static function map(array $fundamentals): array
    {
        $profile = $fundamentals['profile'] ?? [];
        $ratios = $fundamentals['ratios'] ?? [];
        $keyMetrics = $fundamentals['keyMetrics'] ?? [];
        $growth = $fundamentals['growth'] ?? [];
        $cashFlow = $fundamentals['cashFlow'] ?? [];
        $incomeStatementAnnual = $fundamentals['incomeStatementAnnual'] ?? [];

        $freeCashFlow = self::toFloat($cashFlow['freeCashFlow'] ?? null);
        $revenue = self::ttmRevenue($profile, $ratios);

        return [
            'current_price' => self::toFloat($profile['price'] ?? null),
            'market_cap' => self::toFloat($profile['marketCap'] ?? null),
            'revenue_growth' => self::toPercent($growth['revenueGrowth'] ?? null),
            'eps_growth' => self::toPercent($growth['epsgrowth'] ?? $growth['epsGrowth'] ?? null),
            'revenue_cagr_5y' => self::cagr($incomeStatementAnnual, 'revenue'),
            'eps_cagr_5y' => self::cagr($incomeStatementAnnual, 'eps'),
            'free_cash_flow' => $freeCashFlow,
            'fcf_margin' => self::margin($freeCashFlow, $revenue),
            'operating_margin' => self::toPercent($ratios['operatingProfitMarginTTM'] ?? null),
            'net_margin' => self::toPercent($ratios['netProfitMarginTTM'] ?? null),
            'gross_margin' => self::toPercent($ratios['grossProfitMarginTTM'] ?? null),
            'roe' => self::toPercent($keyMetrics['returnOnEquityTTM'] ?? null),
            'roic' => self::toPercent($keyMetrics['returnOnInvestedCapitalTTM'] ?? null),
            'debt_to_ebitda' => self::toFloat($keyMetrics['netDebtToEBITDATTM'] ?? null),
            'interest_coverage' => self::toFloat($ratios['interestCoverageRatioTTM'] ?? null),
            'current_ratio' => self::toFloat($ratios['currentRatioTTM'] ?? $keyMetrics['currentRatioTTM'] ?? null),
            'pe_ratio' => self::toFloat($ratios['priceToEarningsRatioTTM'] ?? null),
            'ev_to_ebitda' => self::toFloat($keyMetrics['evToEBITDATTM'] ?? $ratios['enterpriseValueMultipleTTM'] ?? null),
            'ev_to_fcf' => self::toFloat($keyMetrics['evToFreeCashFlowTTM'] ?? null),
            'price_to_sales' => self::toFloat($ratios['priceToSalesRatioTTM'] ?? null),
            'peg_ratio' => self::toFloat($ratios['priceToEarningsGrowthRatioTTM'] ?? null),
            'fcf_yield' => self::toPercent($keyMetrics['freeCashFlowYieldTTM'] ?? null),
            'indicators_currency' => $profile['currency'] ?? null,
        ];
    }

    /**
     * TTM revenue isn't returned as an absolute figure by any of the -ttm
     * endpoints, only per-share (`revenuePerShareTTM`) - reconstructed here
     * via shares outstanding (market cap / price), the same derivation
     * already used on the frontend's DCF calculator.
     */
    private static function ttmRevenue(array $profile, array $ratios): ?float
    {
        $price = self::toFloat($profile['price'] ?? null);
        $marketCap = self::toFloat($profile['marketCap'] ?? null);
        $revenuePerShare = self::toFloat($ratios['revenuePerShareTTM'] ?? null);

        if ($price === null || $price <= 0 || $marketCap === null || $revenuePerShare === null) {
            return null;
        }

        return $revenuePerShare * ($marketCap / $price);
    }

    private static function margin(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator * 100, 2);
    }

    /**
     * Compound annual growth rate between the oldest and most recent annual
     * statement in the array (FMP returns these newest-first). FMP's free
     * plan caps `limit` at 5 annual statements, so this is really a 4-year
     * CAGR in practice - callers/UI should label it accordingly. Returns
     * null for fewer than 2 data points or a non-positive start/end value
     * (a CAGR through zero or negative earnings isn't meaningful).
     *
     * @param  array<int, array<string, mixed>>  $annualStatements
     */
    private static function cagr(array $annualStatements, string $field): ?float
    {
        $count = count($annualStatements);

        if ($count < 2) {
            return null;
        }

        $latest = self::toFloat($annualStatements[0][$field] ?? null);
        $oldest = self::toFloat($annualStatements[$count - 1][$field] ?? null);

        if ($latest === null || $oldest === null || $latest <= 0 || $oldest <= 0) {
            return null;
        }

        $years = $count - 1;

        return round((($latest / $oldest) ** (1 / $years) - 1) * 100, 2);
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * FMP reports growth/margin/yield figures as a plain fraction (0.157 for
     * 15.7%); stored and shown as the percentage number itself (15.7), same
     * convention as FundamentalIndicatorMapper (EODHD) so switching provider
     * never changes the unit a stored value is in.
     */
    private static function toPercent(mixed $value): ?float
    {
        $float = self::toFloat($value);

        return $float === null ? null : round($float * 100, 2);
    }
}
