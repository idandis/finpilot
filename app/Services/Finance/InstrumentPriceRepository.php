<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Exceptions\Finance\MarketPriceProviderUnavailableException;
use App\Models\InstrumentPrice;

class InstrumentPriceRepository
{
    /**
     * A cached price older than this is considered stale and eligible for
     * a refresh (subject to the caller's remaining daily call budget).
     */
    private const STALE_AFTER_HOURS = 24;

    /**
     * A cached intraday quote older than this is considered stale. Much
     * shorter than STALE_AFTER_HOURS because this feeds a "current price"
     * display, not the daily close - but still not too short, since the
     * provider's own quote is itself only refreshed roughly once a minute
     * and delayed ~15-20 min behind the real market, so polling much more
     * often than this buys nothing.
     */
    private const REALTIME_STALE_AFTER_MINUTES = 10;

    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * Read-only lookup used by the position calculator inside an HTTP
     * request - never triggers an API call.
     */
    public function getCachedPrice(string $isin): ?InstrumentPrice
    {
        return InstrumentPrice::query()->where('isin', $isin)->first();
    }

    /**
     * Used only by the background refresh command. Resolves the symbol
     * (once, ever) and refreshes the price if it's stale, spending at most
     * $callsRemaining API calls. Returns how many calls were actually used,
     * so the caller can track its own daily budget across many ISINs.
     *
     * $force skips the freshness check (used by the manual "refresh now"
     * button, so a click always attempts a real fetch instead of silently
     * no-op'ing within the same 24h window) - the daily call budget still
     * applies regardless, so repeated clicks can never exceed it.
     */
    public function refresh(string $isin, int $callsRemaining, bool $force = false): int
    {
        $record = InstrumentPrice::query()->firstOrCreate(['isin' => $isin]);
        $callsUsed = 0;

        if ($record->code === null) {
            // Crypto never needs an EODHD search call - Trade Republic's own
            // pseudo-ISIN already tells us the ticker, and EODHD's crypto
            // symbol is deterministic from it ("{ticker}-USD" on the ".CC"
            // virtual exchange) - so this also self-heals any ISIN that was
            // marked resolution_failed before this existed, since crypto
            // never actually needed the (failed) search call in the first
            // place.
            $cryptoTicker = TradeDescription::cryptoTicker($isin);

            if ($cryptoTicker !== null) {
                $record->update([
                    'code' => "{$cryptoTicker}-USD",
                    'exchange' => 'CC',
                    'currency' => 'USD',
                    'resolution_failed' => false,
                ]);
            } elseif (! $record->resolution_failed) {
                if ($callsRemaining < 1) {
                    return $callsUsed;
                }

                try {
                    $resolved = $this->provider->resolveSymbol($isin);
                } catch (MarketPriceProviderUnavailableException) {
                    // Couldn't even attempt the call (missing API key, or
                    // the global budget ran out between the caller's own
                    // check above and this one) - transient, so don't spend
                    // a call or mark this ISIN as permanently unresolved.
                    return $callsUsed;
                }

                $callsUsed++;
                $callsRemaining--;

                if ($resolved === null) {
                    $record->update(['resolution_failed' => true]);

                    return $callsUsed;
                }

                $record->update([
                    'code' => $resolved->code,
                    'exchange' => $resolved->exchange,
                    'currency' => $resolved->currency ?? $record->currency,
                ]);
            }
        }

        if ($record->resolution_failed || $record->code === null || $record->exchange === null) {
            return $callsUsed;
        }

        $isFresh = ! $force
            && $record->fetched_at !== null
            && $record->fetched_at->diffInHours(now()) < self::STALE_AFTER_HOURS;

        if ($isFresh || $callsRemaining < 1) {
            return $callsUsed;
        }

        $price = $this->provider->fetchPrice($record->code, $record->exchange);
        $callsUsed++;

        if ($price !== null) {
            $record->update([
                'last_price' => $price->price,
                'price_date' => $price->date,
                'fetched_at' => now(),
            ]);
        }

        return $callsUsed;
    }

    /**
     * Companion to refresh(): keeps a delayed intraday quote alongside the
     * end-of-day close, for an instrument that already has a resolved
     * symbol. Never attempts symbol resolution itself - refresh() owns
     * that, once ever - so an ISIN with no resolved code/exchange yet, or
     * one that failed resolution, is simply skipped here at no cost.
     *
     * $force skips the freshness check, same contract as refresh().
     */
    public function refreshRealtime(string $isin, int $callsRemaining, bool $force = false): int
    {
        $record = InstrumentPrice::query()->where('isin', $isin)->first();

        if ($record === null || $record->resolution_failed || $record->code === null || $record->exchange === null) {
            return 0;
        }

        $isFresh = ! $force
            && $record->realtime_fetched_at !== null
            && $record->realtime_fetched_at->diffInMinutes(now()) < self::REALTIME_STALE_AFTER_MINUTES;

        if ($isFresh || $callsRemaining < 1) {
            return 0;
        }

        $price = $this->provider->fetchRealtimePrice($record->code, $record->exchange);

        if ($price !== null) {
            $record->update([
                'realtime_price' => $price->price,
                'realtime_fetched_at' => now(),
            ]);
        }

        return 1;
    }
}
