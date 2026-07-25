<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface MarketPriceProvider
{
    /**
     * Resolve an ISIN to a tradable symbol (code + exchange) on this
     * provider. Returns null if the ISIN cannot be resolved (unsupported
     * instrument/market on this provider's plan). Costs one API call.
     */
    public function resolveSymbol(string $isin): ?ResolvedSymbol;

    /**
     * Fetch the latest end-of-day price for an already-resolved symbol.
     * Returns null on failure (rate limited, symbol delisted, etc).
     * Costs one API call.
     */
    public function fetchPrice(string $code, string $exchange): ?FetchedPrice;

    /**
     * Fetch the full daily close-price history for an already-resolved
     * symbol within [$from, $to]. Costs one API call regardless of the
     * range's length. Returns null when the call itself failed (retry
     * later), or an empty array when it succeeded but no data exists for
     * the range (e.g. a free-tier plan only exposing the past year) - the
     * caller must tell these two cases apart to know whether to retry.
     *
     * @return array<int, FetchedPrice>|null
     */
    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array;

    /**
     * Fetch the latest news articles mentioning an already-resolved symbol.
     * Substantially more expensive than the other calls (a single ticker
     * lookup costs several API credits on EODHD's plan, unlike the flat
     * one-call cost of the price/history endpoints) - callers must check
     * their remaining budget covers that cost before calling this, not just
     * `>= 1`. Returns null when the call itself failed (retry later), or an
     * empty array when it succeeded but no articles were found.
     *
     * @return array<int, FetchedNewsArticle>|null
     */
    public function fetchNews(string $code, string $exchange): ?array;

    /**
     * Fetch the raw fundamentals payload (Highlights, Valuation, Financials)
     * for a symbol in "{TICKER}.{EXCHANGE}" form. The shape is provider-
     * specific and deeply nested, so it's handed back as a raw decoded
     * array rather than a DTO - callers (FundamentalIndicatorMapper) know
     * how to read it. Substantially more expensive than the price/history
     * endpoints (see EodhdMarketPriceProvider::FUNDAMENTALS_CALL_COST) -
     * callers must check their remaining budget covers that cost before
     * calling this, not just `>= 1`. Returns null when the call itself
     * failed, when the plan doesn't include this endpoint, or when the
     * symbol has no fundamentals data.
     *
     * @return array<string, mixed>|null
     */
    public function fetchFundamentals(string $symbol): ?array;
}
