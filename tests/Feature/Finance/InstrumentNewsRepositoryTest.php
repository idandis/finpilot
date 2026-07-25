<?php

namespace Tests\Feature\Finance;

use App\Contracts\FetchedNewsArticle;
use App\Contracts\FetchedPrice;
use App\Contracts\MarketPriceProvider;
use App\Contracts\ResolvedSymbol;
use App\Models\InstrumentNews;
use App\Models\InstrumentPrice;
use App\Services\Finance\EodhdMarketPriceProvider;
use App\Services\Finance\InstrumentNewsRepository;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstrumentNewsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function resolvedPrice(array $overrides = []): InstrumentPrice
    {
        return InstrumentPrice::factory()->create(array_merge([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'resolution_failed' => false,
        ], $overrides));
    }

    public function test_first_refresh_fetches_and_caches_news_articles()
    {
        $this->resolvedPrice();

        $provider = new FakeNewsProvider(articles: [
            new FetchedNewsArticle(
                title: 'Apple reports strong quarterly earnings',
                publishedAt: Carbon::parse('2026-07-24T09:00:00Z'),
                content: 'Full body...',
                url: 'https://example.com/apple-earnings',
                sentimentPolarity: 0.42,
                tags: ['earnings', 'technology'],
            ),
        ]);

        $repository = new InstrumentNewsRepository($provider);
        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20);

        $this->assertSame(EodhdMarketPriceProvider::NEWS_CALL_COST, $callsUsed);
        $this->assertSame(1, $provider->fetchCalls);
        $this->assertNotNull(InstrumentPrice::where('isin', 'US0378331005')->value('news_fetched_at'));

        $this->assertDatabaseHas('instrument_news', [
            'isin' => 'US0378331005',
            'title' => 'Apple reports strong quarterly earnings',
            'url' => 'https://example.com/apple-earnings',
            'sentiment_polarity' => 0.4200,
        ]);
    }

    public function test_a_fresh_cached_news_set_is_not_refreshed()
    {
        $this->resolvedPrice(['news_fetched_at' => now()]);

        $provider = new FakeNewsProvider;
        $repository = new InstrumentNewsRepository($provider);

        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_force_refreshes_a_fresh_news_set_anyway()
    {
        $this->resolvedPrice(['news_fetched_at' => now()]);

        $provider = new FakeNewsProvider(articles: []);
        $repository = new InstrumentNewsRepository($provider);

        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20, force: true);

        $this->assertSame(EodhdMarketPriceProvider::NEWS_CALL_COST, $callsUsed);
        $this->assertSame(1, $provider->fetchCalls);
    }

    public function test_it_does_nothing_when_the_isin_is_not_yet_resolved()
    {
        $this->resolvedPrice(['code' => null, 'exchange' => null]);

        $provider = new FakeNewsProvider;
        $repository = new InstrumentNewsRepository($provider);

        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_it_does_nothing_when_resolution_failed()
    {
        $this->resolvedPrice(['code' => null, 'exchange' => null, 'resolution_failed' => true]);

        $provider = new FakeNewsProvider;
        $repository = new InstrumentNewsRepository($provider);

        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_it_does_nothing_when_not_enough_budget_remains_for_the_full_cost()
    {
        $this->resolvedPrice();

        $provider = new FakeNewsProvider;
        $repository = new InstrumentNewsRepository($provider);

        // Below NEWS_CALL_COST (10) despite being a healthy budget for any
        // flat-cost endpoint.
        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 5);

        $this->assertSame(0, $callsUsed);
        $this->assertSame(0, $provider->fetchCalls);
    }

    public function test_it_does_not_mark_fetched_at_when_the_api_call_fails()
    {
        $this->resolvedPrice();

        $provider = new FakeNewsProvider(articles: null);
        $repository = new InstrumentNewsRepository($provider);

        $callsUsed = $repository->refresh('US0378331005', callsRemaining: 20);

        $this->assertSame(EodhdMarketPriceProvider::NEWS_CALL_COST, $callsUsed);
        $this->assertNull(InstrumentPrice::where('isin', 'US0378331005')->value('news_fetched_at'));
        $this->assertDatabaseCount('instrument_news', 0);
    }

    public function test_repeated_refresh_updates_existing_articles_by_url_instead_of_duplicating()
    {
        $this->resolvedPrice();

        $article = new FetchedNewsArticle(
            title: 'Original title',
            publishedAt: Carbon::parse('2026-07-24T09:00:00Z'),
            url: 'https://example.com/same-article',
        );

        $repository = new InstrumentNewsRepository(new FakeNewsProvider(articles: [$article]));
        $repository->refresh('US0378331005', callsRemaining: 20);

        $updatedArticle = new FetchedNewsArticle(
            title: 'Updated title',
            publishedAt: Carbon::parse('2026-07-24T09:00:00Z'),
            url: 'https://example.com/same-article',
        );
        $repository = new InstrumentNewsRepository(new FakeNewsProvider(articles: [$updatedArticle]));
        $repository->refresh('US0378331005', callsRemaining: 20, force: true);

        $this->assertDatabaseCount('instrument_news', 1);
        $this->assertDatabaseHas('instrument_news', ['url' => 'https://example.com/same-article', 'title' => 'Updated title']);
    }

    public function test_get_cached_orders_articles_by_published_at_descending()
    {
        InstrumentNews::factory()->create(['isin' => 'US0378331005', 'published_at' => '2026-07-20T00:00:00Z']);
        InstrumentNews::factory()->create(['isin' => 'US0378331005', 'published_at' => '2026-07-24T00:00:00Z']);

        $repository = new InstrumentNewsRepository(new FakeNewsProvider);
        $articles = $repository->getCached('US0378331005');

        $this->assertCount(2, $articles);
        $this->assertSame('2026-07-24', $articles->first()->published_at->format('Y-m-d'));
    }

    public function test_status_for_reports_resolvable_and_fetched_at()
    {
        $this->resolvedPrice(['news_fetched_at' => '2026-07-24 08:00:00']);
        InstrumentNews::factory()->create(['isin' => 'US0378331005']);

        $repository = new InstrumentNewsRepository(new FakeNewsProvider);
        $status = $repository->statusFor('US0378331005');

        $this->assertTrue($status['resolvable']);
        $this->assertNotNull($status['fetched_at']);
        $this->assertCount(1, $status['articles']);
    }

    public function test_status_for_reports_not_resolvable_when_the_isin_has_never_been_seen()
    {
        $repository = new InstrumentNewsRepository(new FakeNewsProvider);
        $status = $repository->statusFor('XX0000000000');

        $this->assertFalse($status['resolvable']);
        $this->assertNull($status['fetched_at']);
        $this->assertCount(0, $status['articles']);
    }
}

class FakeNewsProvider implements MarketPriceProvider
{
    public int $fetchCalls = 0;

    /**
     * @param  array<int, FetchedNewsArticle>|null  $articles
     */
    public function __construct(private readonly ?array $articles = []) {}

    public function resolveSymbol(string $isin): ?ResolvedSymbol
    {
        return null;
    }

    public function fetchPrice(string $code, string $exchange): ?FetchedPrice
    {
        return null;
    }

    public function fetchHistory(string $code, string $exchange, CarbonInterface $from, CarbonInterface $to): ?array
    {
        return null;
    }

    public function fetchNews(string $code, string $exchange): ?array
    {
        $this->fetchCalls++;

        return $this->articles;
    }

    public function fetchFundamentals(string $symbol): ?array
    {
        return null;
    }
}
