<?php

namespace App\Services\Finance;

use App\Contracts\MarketPriceProvider;
use App\Models\InstrumentNews;
use App\Models\InstrumentPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InstrumentNewsRepository
{
    /**
     * A cached news set older than this is considered stale and eligible
     * for a refresh (subject to the caller's remaining daily call budget).
     */
    private const STALE_AFTER_HOURS = 24;

    private const ARTICLES_LIMIT = 25;

    public function __construct(private readonly MarketPriceProvider $provider) {}

    /**
     * Read-only lookup used by the position page - never triggers an API
     * call.
     *
     * @return Collection<int, InstrumentNews>
     */
    public function getCached(string $isin): Collection
    {
        return InstrumentNews::query()
            ->where('isin', $isin)
            ->orderByDesc('published_at')
            ->limit(self::ARTICLES_LIMIT)
            ->get();
    }

    /**
     * Everything the position page's News tab needs in one call. Fetching
     * is only possible once the symbol has already been resolved by the
     * price refresh flow - this repository never resolves a symbol itself,
     * that's InstrumentPriceRepository's job.
     *
     * @return array{articles: Collection<int, InstrumentNews>, fetched_at: Carbon|null, resolvable: bool}
     */
    public function statusFor(string $isin): array
    {
        $price = InstrumentPrice::query()->where('isin', $isin)->first();

        return [
            'articles' => $this->getCached($isin),
            'fetched_at' => $price?->news_fetched_at,
            'resolvable' => $price !== null
                && ! $price->resolution_failed
                && $price->code !== null
                && $price->exchange !== null,
        ];
    }

    /**
     * Fetch and cache the latest news for an already-resolved ISIN, subject
     * to the shared daily call budget. The news endpoint costs several
     * calls at once (see EodhdMarketPriceProvider::NEWS_CALL_COST), so
     * $callsRemaining must cover that whole cost, not just ">= 1". Returns
     * how many calls were actually used, mirroring
     * InstrumentPriceRepository::refresh()'s contract.
     *
     * $force skips the staleness check (an explicit "load news" click) -
     * the daily call budget still applies regardless.
     */
    public function refresh(string $isin, int $callsRemaining, bool $force = false): int
    {
        $price = InstrumentPrice::query()->where('isin', $isin)->first();

        if ($price === null || $price->resolution_failed || $price->code === null || $price->exchange === null) {
            return 0;
        }

        $isFresh = ! $force
            && $price->news_fetched_at !== null
            && $price->news_fetched_at->diffInHours(now()) < self::STALE_AFTER_HOURS;

        if ($isFresh || $callsRemaining < EodhdMarketPriceProvider::NEWS_CALL_COST) {
            return 0;
        }

        $articles = $this->provider->fetchNews($price->code, $price->exchange);

        if ($articles === null) {
            return EodhdMarketPriceProvider::NEWS_CALL_COST;
        }

        $price->update(['news_fetched_at' => now()]);

        foreach ($articles as $article) {
            $attributes = [
                'published_at' => $article->publishedAt,
                'title' => $article->title,
                'content' => $article->content,
                'sentiment_polarity' => $article->sentimentPolarity,
                'tags' => $article->tags,
            ];

            if ($article->url !== null) {
                InstrumentNews::query()->updateOrCreate(
                    ['isin' => $isin, 'url' => $article->url],
                    $attributes,
                );
            } else {
                InstrumentNews::query()->create(['isin' => $isin, 'url' => null, ...$attributes]);
            }
        }

        return EodhdMarketPriceProvider::NEWS_CALL_COST;
    }
}
