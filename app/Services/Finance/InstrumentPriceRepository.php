<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\InstrumentPrice;

class InstrumentPriceRepository
{
    /**
     * A cached price older than this is considered stale and eligible for
     * a refresh (subject to the caller's remaining daily call budget).
     */
    private const STALE_AFTER_HOURS = 24;

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

                $resolved = $this->provider->resolveSymbol($isin);
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
}
