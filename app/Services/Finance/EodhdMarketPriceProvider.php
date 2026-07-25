<?php

namespace App\Services\Finance;

use App\Contracts\FetchedNewsArticle;
use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class EodhdMarketPriceProvider implements MarketPriceProvider
{
    private const BASE_URL = 'https://eodhd.com/api';

    /**
     * The news endpoint bills 5 calls flat plus 5 per ticker looked up - for
     * our single-symbol lookups that's 10, versus the flat 1 call every
     * other endpoint here costs. Budget checks for this endpoint must use
     * this constant, not the implicit "1" the other methods check for.
     */
    public const NEWS_CALL_COST = 10;

    /**
     * EODHD bills the fundamentals endpoint at 10 calls per request,
     * regardless of ticker.
     */
    public const FUNDAMENTALS_CALL_COST = 10;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly EodhdCallBudget $budget,
    ) {}

    public function resolveSymbol(string $isin): ?ResolvedSymbol
    {
        if (! $this->apiKey || $this->budget->remaining() < 1) {
            return null;
        }

        $response = Http::get(self::BASE_URL."/search/{$isin}", [
            'api_token' => $this->apiKey,
            'fmt' => 'json',
        ]);
        $this->budget->increment();

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        $results = $response->json();

        // The same ISIN is often cross-listed on several exchanges, each in
        // its own currency - and some of those (LSE listings in particular)
        // report their "Currency" as GBP while actually quoting in pence,
        // 100x too large. Preferring a EUR listing when one exists avoids
        // that ambiguity entirely, on top of skipping the FX conversion.
        $best = collect($results)->first(fn ($result) => ($result['Currency'] ?? null) === 'EUR') ?? $results[0];

        if (empty($best['Code']) || empty($best['Exchange'])) {
            return null;
        }

        return new ResolvedSymbol(
            code: $best['Code'],
            exchange: $best['Exchange'],
            name: $best['Name'] ?? null,
            currency: $best['Currency'] ?? null,
        );
    }

    public function fetchPrice(string $code, string $exchange): ?FetchedPrice
    {
        if (! $this->apiKey || $this->budget->remaining() < 1) {
            return null;
        }

        $response = Http::get(self::BASE_URL."/eod/{$code}.{$exchange}", [
            'api_token' => $this->apiKey,
            'fmt' => 'json',
            'order' => 'd',
            'period' => 'd',
        ]);
        $this->budget->increment();

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        $latest = $response->json()[0];

        if (! isset($latest['close'], $latest['date'])) {
            return null;
        }

        return new FetchedPrice(
            price: (float) $latest['close'],
            date: Carbon::parse($latest['date']),
        );
    }

    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array
    {
        if (! $this->apiKey || $this->budget->remaining() < 1) {
            return null;
        }

        $response = Http::get(self::BASE_URL."/eod/{$code}.{$exchange}", [
            'api_token' => $this->apiKey,
            'fmt' => 'json',
            'order' => 'd',
            'period' => 'd',
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
        $this->budget->increment();

        if ($response->failed()) {
            return null;
        }

        return collect($response->json() ?? [])
            ->filter(fn ($row) => isset($row['close'], $row['date']))
            ->map(fn ($row) => new FetchedPrice(
                price: (float) $row['close'],
                date: Carbon::parse($row['date']),
                open: isset($row['open']) ? (float) $row['open'] : null,
                high: isset($row['high']) ? (float) $row['high'] : null,
                low: isset($row['low']) ? (float) $row['low'] : null,
            ))
            ->values()
            ->all();
    }

    public function fetchNews(string $code, string $exchange): ?array
    {
        if (! $this->apiKey || $this->budget->remaining() < self::NEWS_CALL_COST) {
            return null;
        }

        $response = Http::get(self::BASE_URL.'/news', [
            'api_token' => $this->apiKey,
            'fmt' => 'json',
            's' => "{$code}.{$exchange}",
            'limit' => 25,
        ]);
        $this->budget->increment(self::NEWS_CALL_COST);

        if ($response->failed()) {
            return null;
        }

        return collect($response->json() ?? [])
            ->filter(fn ($row) => isset($row['title'], $row['date']))
            ->map(fn ($row) => new FetchedNewsArticle(
                title: $row['title'],
                publishedAt: Carbon::parse($row['date']),
                content: $row['content'] ?? null,
                url: $row['link'] ?? null,
                sentimentPolarity: isset($row['sentiment']['polarity']) ? (float) $row['sentiment']['polarity'] : null,
                tags: $row['tags'] ?? null,
            ))
            ->values()
            ->all();
    }

    public function fetchFundamentals(string $symbol): ?array
    {
        if (! $this->apiKey || $this->budget->remaining() < self::FUNDAMENTALS_CALL_COST) {
            return null;
        }

        $response = Http::get(self::BASE_URL."/fundamentals/{$symbol}", [
            'api_token' => $this->apiKey,
            'fmt' => 'json',
        ]);
        $this->budget->increment(self::FUNDAMENTALS_CALL_COST);

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        return $response->json();
    }
}
