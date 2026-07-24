<?php

namespace App\Services\Finance;

/**
 * Recognizes Trade Republic's own transaction description formats - shared
 * between the PDF importer (which parses these out of a fresh statement) and
 * the position calculator (which re-parses them from already-stored
 * descriptions), so both stay in sync with a single pattern.
 */
class TradeDescription
{
    /**
     * Matches "Buy trade|Sell trade|Savings plan execution", e.g. "Buy trade
     * IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF
     * (USD) Accumulating, quantity: 0.344482" - captures the ISIN, the
     * instrument name and the traded quantity. An optional leading
     * "Cancellation" marks a reversal of the trade that follows it.
     */
    private const TRADE_PATTERN = '/^(?:Cancellation\s+)?(?:Buy trade|Sell trade|Savings plan execution)\s+([A-Z]{2}[A-Z0-9]{10})\s+(.+?),\s*quantity:\s*([\d.]+)$/';

    /**
     * Matches "Cash Dividend for ISIN {ISIN}" - the only row format that
     * carries an ISIN outside of a trade. It has no quantity in the text.
     */
    private const DIVIDEND_PATTERN = '/^Cash Dividend for ISIN\s+([A-Z]{2}[A-Z0-9]{10})$/';

    /**
     * @return array{isin: string|null, name: string|null, quantity: float|null}
     */
    public static function parseTrade(string $description): array
    {
        if (! preg_match(self::TRADE_PATTERN, $description, $match)) {
            return ['isin' => null, 'name' => null, 'quantity' => null];
        }

        return ['isin' => $match[1], 'name' => $match[2], 'quantity' => (float) $match[3]];
    }

    /**
     * @return array{isin: string|null}
     */
    public static function parseDividend(string $description): array
    {
        if (! preg_match(self::DIVIDEND_PATTERN, $description, $match)) {
            return ['isin' => null];
        }

        return ['isin' => $match[1]];
    }

    public static function isTradeRow(string $description): bool
    {
        return (bool) preg_match(self::TRADE_PATTERN, $description);
    }

    /**
     * Trade Republic has no real ISIN for crypto (it doesn't have one to
     * begin with), so it invents a pseudo-ISIN starting with "XF" - not a
     * real ISO 3166 country prefix, which is exactly why it's safe to use
     * as a crypto signal. This is a different, narrower check than
     * InstrumentPrice::resolution_failed: that flag just means "no market
     * data provider could resolve this", which could in principle also be
     * true for a real, currently-unsupported security.
     */
    public static function isCrypto(string $isin): bool
    {
        return str_starts_with($isin, 'XF');
    }

    /**
     * Trade Republic's crypto pseudo-ISIN embeds the coin's own ticker
     * right in the middle, e.g. "XF000BTC0017" -> "BTC", "XF000ETH0019" ->
     * "ETH" - which is also EODHD's crypto ticker on its ".CC" virtual
     * exchange (as "{ticker}-USD"), so the two line up for free. Returns
     * null for a non-crypto ISIN or a crypto pseudo-ISIN that doesn't
     * match this shape (never guess a ticker from something we can't
     * parse confidently).
     */
    public static function cryptoTicker(string $isin): ?string
    {
        if (! self::isCrypto($isin) || ! preg_match('/^XF000([A-Z]{2,5})\d+$/', $isin, $match)) {
            return null;
        }

        return $match[1];
    }

    /**
     * "Buy trade" and "Savings plan execution" add to a position; "Sell
     * trade" removes from it. A leading "Cancellation" flips that effect,
     * since it reverses whichever of the two just happened.
     */
    public static function increasesPosition(string $description): bool
    {
        $isCancellation = str_starts_with($description, 'Cancellation ');
        $isSell = str_contains($description, 'Sell trade');

        return $isSell ? $isCancellation : ! $isCancellation;
    }
}
