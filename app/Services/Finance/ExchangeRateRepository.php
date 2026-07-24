<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\ExchangeRate;

class ExchangeRateRepository
{
    /**
     * A cached rate older than this is considered stale and eligible for a
     * refresh (subject to the caller's remaining daily call budget).
     */
    private const STALE_AFTER_HOURS = 24;

    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * Read-only lookup used by the position calculator inside an HTTP
     * request - never triggers an API call.
     */
    public function getCachedRate(string $currency): ?ExchangeRate
    {
        return ExchangeRate::query()->where('currency', $currency)->first();
    }

    /**
     * Used only by the background refresh command. Unlike an instrument's
     * ISIN, a currency's EODHD forex symbol is deterministic
     * ("{currency}EUR.FOREX") - there is no separate resolution step, so a
     * refresh costs at most a single API call. Returns how many calls were
     * actually used, so the caller can track its own daily budget.
     *
     * $force skips the freshness check (used by the manual "refresh now"
     * button, so a click always attempts a real fetch instead of silently
     * no-op'ing within the same 24h window) - the daily call budget still
     * applies regardless, so repeated clicks can never exceed it.
     */
    public function refresh(string $currency, int $callsRemaining, bool $force = false): int
    {
        $record = ExchangeRate::query()->firstOrCreate(['currency' => $currency]);

        $isFresh = ! $force
            && $record->fetched_at !== null
            && $record->fetched_at->diffInHours(now()) < self::STALE_AFTER_HOURS;

        if ($isFresh || $callsRemaining < 1) {
            return 0;
        }

        $rate = $this->provider->fetchPrice("{$currency}EUR", 'FOREX');

        if ($rate !== null) {
            $record->update([
                'rate_to_eur' => $rate->price,
                'rate_date' => $rate->date,
                'fetched_at' => now(),
            ]);
        }

        return 1;
    }
}
