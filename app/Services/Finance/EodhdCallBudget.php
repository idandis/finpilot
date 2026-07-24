<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks how many EODHD API calls have actually been made today, shared
 * across every entry point (the scheduled command, a manual "refresh now"
 * click, artisan invoked directly, ...) so none of them can push the total
 * past the plan's daily limit even when combined.
 */
class EodhdCallBudget
{
    public function __construct(private readonly int $dailyLimit) {}

    public function remaining(): int
    {
        return max(0, $this->dailyLimit - $this->used());
    }

    public function used(): int
    {
        return (int) Cache::get($this->cacheKey(), 0);
    }

    /**
     * Most endpoints cost a flat 1 call; the news feed costs several (see
     * EodhdMarketPriceProvider::NEWS_CALL_COST), hence the optional $by.
     */
    public function increment(int $by = 1): void
    {
        Cache::add($this->cacheKey(), 0, now()->endOfDay());
        Cache::increment($this->cacheKey(), $by);
    }

    private function cacheKey(): string
    {
        return 'eodhd:calls_used:'.now()->toDateString();
    }
}
