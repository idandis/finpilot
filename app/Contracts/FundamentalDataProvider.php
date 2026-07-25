<?php

namespace App\Contracts;

/**
 * Deliberately narrower than MarketPriceProvider: this app's fundamentals
 * source (FMP) is unrelated to its prices/history/news source (EODHD), so
 * it gets its own small contract instead of forcing an implementation to
 * stub out four unrelated methods it will never be asked for.
 */
interface FundamentalDataProvider
{
    /**
     * Fetch the raw fundamentals payload for a symbol. The shape is
     * provider-specific, so it's handed back as a raw decoded array rather
     * than a DTO - callers (FmpIndicatorMapper) know how to read it. Returns
     * null when the symbol can't be resolved at all (bad API key, unknown
     * ticker) - a partially-populated payload (some sections missing) is
     * still returned rather than null, since individual indicators degrade
     * gracefully to null further down the pipeline.
     *
     * @return array<string, mixed>|null
     */
    public function fetchFundamentals(string $symbol): ?array;
}
