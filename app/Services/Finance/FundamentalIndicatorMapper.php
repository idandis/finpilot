<?php

namespace App\Services\Finance;

/**
 * Turns the raw, deeply-nested EODHD /fundamentals payload into the 10
 * indicators tracked per company analysis. Every lookup is null-safe: a
 * missing section (unsupported instrument, thinner data on some exchanges)
 * simply yields a null indicator rather than an error, consistent with the
 * rest of the app's "never break the page over missing market data" rule.
 */
class FundamentalIndicatorMapper
{
    /**
     * EBIT isn't reported net of tax, and the effective tax rate isn't
     * reliably present on this endpoint for every market - a flat assumed
     * rate keeps ROIC a rough, comparable estimate rather than a precise
     * figure (surfaced as such in the UI, not presented as exact).
     */
    private const ASSUMED_TAX_RATE = 0.21;

    /**
     * @param  array<string, mixed>  $fundamentals
     * @return array<string, mixed>
     */
    public static function map(array $fundamentals): array
    {
        $highlights = $fundamentals['Highlights'] ?? [];
        $valuation = $fundamentals['Valuation'] ?? [];
        $general = $fundamentals['General'] ?? [];

        $balanceSheet = self::latestYearly($fundamentals, 'Balance_Sheet');
        $cashFlow = self::latestYearly($fundamentals, 'Cash_Flow');
        $incomeStatement = self::latestYearly($fundamentals, 'Income_Statement');

        $ebitda = self::toFloat($highlights['EBITDA'] ?? null);
        $totalDebt = self::totalDebt($balanceSheet);
        $freeCashFlow = self::freeCashFlow($cashFlow);
        $marketCap = self::toFloat($highlights['MarketCapitalization'] ?? null);

        return [
            'revenue_growth' => self::toPercent($highlights['QuarterlyRevenueGrowthYOY'] ?? null),
            'eps_growth' => self::toPercent($highlights['QuarterlyEarningsGrowthYOY'] ?? null),
            'free_cash_flow' => $freeCashFlow,
            'operating_margin' => self::toPercent($highlights['OperatingMarginTTM'] ?? null),
            'roe' => self::toPercent($highlights['ReturnOnEquityTTM'] ?? null),
            'roic' => self::roic($incomeStatement, $balanceSheet),
            'debt_to_ebitda' => ($totalDebt !== null && $ebitda) ? round($totalDebt / $ebitda, 4) : null,
            'pe_ratio' => self::toFloat($highlights['PERatio'] ?? $valuation['TrailingPE'] ?? null),
            'ev_to_ebitda' => self::toFloat($valuation['EnterpriseValueEbitda'] ?? null),
            'peg_ratio' => self::toFloat($highlights['PEGRatio'] ?? null),
            'fcf_yield' => ($freeCashFlow !== null && $marketCap) ? round(($freeCashFlow / $marketCap) * 100, 2) : null,
            'indicators_currency' => $general['CurrencyCode'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fundamentals
     * @return array<string, mixed>
     */
    private static function latestYearly(array $fundamentals, string $statement): array
    {
        $yearly = $fundamentals['Financials'][$statement]['yearly'] ?? [];

        if (! is_array($yearly) || $yearly === []) {
            return [];
        }

        ksort($yearly);

        return (array) end($yearly);
    }

    /**
     * @param  array<string, mixed>  $cashFlow
     */
    private static function freeCashFlow(array $cashFlow): ?float
    {
        if (isset($cashFlow['freeCashFlow'])) {
            return self::toFloat($cashFlow['freeCashFlow']);
        }

        $operating = self::toFloat($cashFlow['totalCashFromOperatingActivities'] ?? null);
        $capex = self::toFloat($cashFlow['capitalExpenditures'] ?? null);

        if ($operating === null || $capex === null) {
            return null;
        }

        // capitalExpenditures is reported as a negative outflow.
        return round($operating + $capex, 2);
    }

    /**
     * @param  array<string, mixed>  $balanceSheet
     */
    private static function totalDebt(array $balanceSheet): ?float
    {
        if (isset($balanceSheet['shortLongTermDebtTotal'])) {
            return self::toFloat($balanceSheet['shortLongTermDebtTotal']);
        }

        $longTerm = self::toFloat($balanceSheet['longTermDebt'] ?? null);
        $shortTerm = self::toFloat($balanceSheet['shortTermDebt'] ?? null);

        if ($longTerm === null && $shortTerm === null) {
            return null;
        }

        return round(($longTerm ?? 0) + ($shortTerm ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $incomeStatement
     * @param  array<string, mixed>  $balanceSheet
     */
    private static function roic(array $incomeStatement, array $balanceSheet): ?float
    {
        $ebit = self::toFloat($incomeStatement['ebit'] ?? null);
        $equity = self::toFloat($balanceSheet['totalStockholderEquity'] ?? null);
        $cash = self::toFloat($balanceSheet['cash'] ?? null) ?? 0.0;
        $debt = self::totalDebt($balanceSheet) ?? 0.0;

        if ($ebit === null || $equity === null) {
            return null;
        }

        $investedCapital = $debt + $equity - $cash;

        if ($investedCapital <= 0) {
            return null;
        }

        $nopat = $ebit * (1 - self::ASSUMED_TAX_RATE);

        return round(($nopat / $investedCapital) * 100, 2);
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === 'NA') {
            return null;
        }

        return (float) $value;
    }

    /**
     * EODHD reports growth/margin figures as a plain fraction (0.157 for
     * 15.7%); stored and shown as the percentage number itself (15.7) so
     * manual entry and auto-fill use the same, human-friendly unit.
     */
    private static function toPercent(mixed $value): ?float
    {
        $float = self::toFloat($value);

        return $float === null ? null : round($float * 100, 2);
    }
}
