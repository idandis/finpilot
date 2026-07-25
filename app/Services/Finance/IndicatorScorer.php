<?php

namespace App\Services\Finance;

/**
 * Turns each raw indicator value into a 1-10 "how good is this for buying"
 * score, using the same threshold bands already shown to the user as plain
 * text next to each field (see CompanyAnalyses/Show.vue). These are
 * indicative bands, not a precise formula - they exist to give a quick
 * at-a-glance read, not a certified verdict.
 */
class IndicatorScorer
{
    /**
     * free_cash_flow is an absolute currency amount with no
     * company-size-independent threshold, so it's deliberately not scored
     * here (same reasoning as its "no range" note in the UI).
     *
     * @var array<int, string>
     */
    public const SCOREABLE = [
        'revenue_growth', 'eps_growth', 'revenue_cagr_5y', 'eps_cagr_5y',
        'operating_margin', 'net_margin', 'gross_margin', 'roe', 'roic',
        'debt_to_ebitda', 'interest_coverage', 'current_ratio',
        'pe_ratio', 'ev_to_ebitda', 'ev_to_fcf', 'price_to_sales', 'peg_ratio', 'fcf_yield', 'fcf_margin',
    ];

    public static function score(string $indicator, ?float $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return match ($indicator) {
            'revenue_growth' => self::band($value, [[0, 2], [5, 4], [15, 7]], 10),
            'eps_growth' => self::band($value, [[0, 2], [10, 4], [20, 7]], 10),
            'revenue_cagr_5y' => self::band($value, [[0, 2], [5, 4], [15, 7]], 10),
            'eps_cagr_5y' => self::band($value, [[0, 2], [10, 4], [20, 7]], 10),
            'operating_margin' => self::band($value, [[10, 3], [20, 5], [30, 7]], 10),
            'net_margin' => self::band($value, [[5, 2], [10, 4], [20, 7]], 10),
            'gross_margin' => self::band($value, [[20, 2], [40, 4], [60, 7]], 10),
            'roe' => self::band($value, [[10, 3], [15, 5], [20, 7]], 10),
            'roic' => self::band($value, [[5, 2], [10, 5], [15, 7]], 10),
            'fcf_yield' => self::band($value, [[3, 3], [6, 6]], 9),
            'fcf_margin' => self::band($value, [[5, 2], [10, 4], [20, 7]], 10),
            'debt_to_ebitda' => self::band($value, [[1, 10], [2, 8], [4, 5]], 2),
            'interest_coverage' => self::band($value, [[2, 2], [5, 4], [10, 7]], 10),
            'current_ratio' => self::band($value, [[1, 2], [1.5, 4], [2, 7]], 10),
            'pe_ratio' => self::band($value, [[15, 9], [25, 7], [35, 5], [50, 3]], 2),
            'ev_to_ebitda' => self::band($value, [[10, 9], [15, 7], [20, 5]], 3),
            'ev_to_fcf' => self::band($value, [[15, 9], [25, 7], [35, 5], [50, 3]], 2),
            'price_to_sales' => self::band($value, [[1, 9], [3, 7], [6, 5], [10, 3]], 2),
            'peg_ratio' => self::band($value, [[1, 9], [1.5, 7], [2, 5]], 3),
            default => null,
        };
    }

    /**
     * Walks ascending [upperBound, score] pairs (a plain list, not an
     * associative array, since PHP silently truncates float array keys to
     * int - fatal for bands like 1.5) and returns the score for the first
     * band $value falls under, or $aboveAllBandsScore if it exceeds every
     * band. Works for both "higher is better" (ascending scores, e.g.
     * revenue growth) and "lower is better" (descending scores, e.g. P/E)
     * indicators alike - the band values themselves already encode which
     * direction is good.
     *
     * @param  array<int, array{0: int|float, 1: int}>  $bands
     */
    private static function band(float $value, array $bands, int $aboveAllBandsScore): int
    {
        foreach ($bands as [$upperBound, $score]) {
            if ($value < $upperBound) {
                return $score;
            }
        }

        return $aboveAllBandsScore;
    }
}
