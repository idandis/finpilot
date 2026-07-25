<?php

namespace App\Services\Finance;

use App\Contracts\FundamentalDataProvider;
use Illuminate\Support\Facades\Http;

class FmpFundamentalDataProvider implements FundamentalDataProvider
{
    /**
     * FMP retired its legacy "/api/v3/{symbol}" endpoints for accounts
     * created after August 2025 in favor of this "stable" API, which takes
     * the symbol as a "?symbol=" query param instead of a path segment.
     */
    private const BASE_URL = 'https://financialmodelingprep.com/stable';

    public function __construct(private readonly ?string $apiKey) {}

    public function fetchFundamentals(string $symbol): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        $fmpSymbol = self::normalizeSymbol($symbol);

        // The company profile call also confirms the symbol actually
        // resolves on FMP - if this one is empty/fails, nothing else here
        // is going to be usable either, so that's the only call whose
        // failure makes the whole fetch a null (the others degrade
        // individually via FmpIndicatorMapper's null-safe field lookups).
        $profile = $this->fetchFirst('/profile', $fmpSymbol);

        if ($profile === null) {
            return null;
        }

        return [
            'profile' => $profile,
            'ratios' => $this->fetchFirst('/ratios-ttm', $fmpSymbol) ?? [],
            'keyMetrics' => $this->fetchFirst('/key-metrics-ttm', $fmpSymbol) ?? [],
            'growth' => $this->fetchFirst('/financial-growth', $fmpSymbol) ?? [],
            'cashFlow' => $this->fetchFirst('/cash-flow-statement', $fmpSymbol) ?? [],
            // Used only to compute 5-year revenue/EPS CAGR - the free plan
            // caps `limit` at 5 years of annual statements, so this yields a
            // 4-year CAGR in practice (FmpIndicatorMapper labels it as such).
            'incomeStatementAnnual' => $this->fetchAll('/income-statement', $fmpSymbol, ['period' => 'annual', 'limit' => 5]) ?? [],
        ];
    }

    /**
     * FMP's -ttm/statement endpoints all return a JSON array; the first
     * (most recent) element is the one we want. Returns null on any HTTP
     * failure or an empty array, so callers can tell "nothing here" apart
     * from "this section wasn't even attempted".
     *
     * @return array<string, mixed>|null
     */
    private function fetchFirst(string $path, string $symbol): ?array
    {
        $data = $this->fetchAll($path, $symbol);

        return $data === null ? null : ($data[0] ?? null);
    }

    /**
     * Same degradation contract as fetchFirst(), but returns the whole
     * response array instead of just its first element - needed for
     * multi-period statements like income-statement, where every year
     * fetched matters, not only the most recent one.
     *
     * @param  array<string, mixed>  $extraParams
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchAll(string $path, string $symbol, array $extraParams = []): ?array
    {
        $response = Http::get(self::BASE_URL.$path, ['symbol' => $symbol, 'apikey' => $this->apiKey, ...$extraParams]);

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        return $response->json();
    }

    /**
     * This app's other symbols follow EODHD's "{TICKER}.{EXCHANGE}"
     * convention (e.g. "AAPL.US"), but FMP expects a bare ticker for US
     * listings. Stripping a trailing ".US" covers the common case; other
     * exchange suffixes are passed through as-is since FMP uses its own
     * (different) suffix scheme for non-US listings that isn't safe to
     * guess-translate.
     */
    public static function normalizeSymbol(string $symbol): string
    {
        return preg_replace('/\.US$/i', '', $symbol);
    }
}
