<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\EodhdMarketPriceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EodhdMarketPriceProviderTest extends TestCase
{
    /**
     * A budget generous enough to never be the limiting factor in tests
     * that are exercising other behavior.
     */
    private function provider(string $apiKey = 'fake-token'): EodhdMarketPriceProvider
    {
        return new EodhdMarketPriceProvider($apiKey, new EodhdCallBudget(dailyLimit: 100));
    }

    public function test_it_resolves_a_symbol_from_an_isin()
    {
        Http::fake([
            'eodhd.com/api/search/*' => Http::response([
                ['Code' => 'VWCE', 'Exchange' => 'XETRA', 'Name' => 'Vanguard FTSE All-World', 'Currency' => 'EUR'],
            ]),
        ]);

        $provider = $this->provider();
        $resolved = $provider->resolveSymbol('IE00BK5BQT80');

        $this->assertNotNull($resolved);
        $this->assertSame('VWCE', $resolved->code);
        $this->assertSame('XETRA', $resolved->exchange);
        $this->assertSame('EUR', $resolved->currency);
    }

    public function test_it_prefers_a_eur_listing_when_the_isin_is_cross_listed_on_several_exchanges()
    {
        Http::fake([
            'eodhd.com/api/search/*' => Http::response([
                ['Code' => 'GLDA', 'Exchange' => 'LSE', 'Name' => 'Amundi Physical Gold ETC C', 'Currency' => 'GBP'],
                ['Code' => 'GLDD', 'Exchange' => 'LSE', 'Name' => 'Amundi Physical Gold ETC C USD', 'Currency' => 'USD'],
                ['Code' => 'GOLD', 'Exchange' => 'PA', 'Name' => 'Amundi Physical Gold ETC C EUR', 'Currency' => 'EUR'],
            ]),
        ]);

        $provider = $this->provider();
        $resolved = $provider->resolveSymbol('FR0013416716');

        $this->assertNotNull($resolved);
        $this->assertSame('GOLD', $resolved->code);
        $this->assertSame('PA', $resolved->exchange);
        $this->assertSame('EUR', $resolved->currency);
    }

    public function test_it_falls_back_to_the_first_result_when_no_eur_listing_exists()
    {
        Http::fake([
            'eodhd.com/api/search/*' => Http::response([
                ['Code' => 'AAPL', 'Exchange' => 'US', 'Name' => 'Apple Inc', 'Currency' => 'USD'],
            ]),
        ]);

        $provider = $this->provider();
        $resolved = $provider->resolveSymbol('US0378331005');

        $this->assertNotNull($resolved);
        $this->assertSame('AAPL', $resolved->code);
        $this->assertSame('USD', $resolved->currency);
    }

    public function test_it_returns_null_when_the_isin_is_not_found()
    {
        Http::fake([
            'eodhd.com/api/search/*' => Http::response([]),
        ]);

        $provider = $this->provider();

        $this->assertNull($provider->resolveSymbol('XX0000000000'));
    }

    public function test_it_returns_null_when_the_search_request_fails()
    {
        Http::fake([
            'eodhd.com/api/search/*' => Http::response(null, 500),
        ]);

        $provider = $this->provider();

        $this->assertNull($provider->resolveSymbol('IE00BK5BQT80'));
    }

    public function test_it_fetches_the_latest_end_of_day_price()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-22', 'close' => 105.32],
            ]),
        ]);

        $provider = $this->provider();
        $price = $provider->fetchPrice('VWCE', 'XETRA');

        $this->assertNotNull($price);
        $this->assertSame(105.32, $price->price);
        $this->assertSame('2026-07-22', $price->date->format('Y-m-d'));
    }

    public function test_it_returns_null_when_the_price_request_fails()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response(null, 500),
        ]);

        $provider = $this->provider();

        $this->assertNull($provider->fetchPrice('VWCE', 'XETRA'));
    }

    public function test_it_refuses_to_call_the_api_when_the_daily_budget_is_exhausted()
    {
        Http::fake();

        $provider = new EodhdMarketPriceProvider('fake-token', new EodhdCallBudget(dailyLimit: 0));

        $this->assertNull($provider->resolveSymbol('IE00BK5BQT80'));
        $this->assertNull($provider->fetchPrice('VWCE', 'XETRA'));
        $this->assertNull($provider->fetchHistory('VWCE', 'XETRA', Carbon::parse('2026-01-01'), Carbon::parse('2026-07-23')));
        $this->assertNull($provider->fetchNews('VWCE', 'XETRA'));
        Http::assertNothingSent();
    }

    public function test_it_fetches_a_full_price_history_with_from_and_to_params()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-20', 'close' => 100.0],
                ['date' => '2026-07-21', 'close' => 102.5],
                ['date' => '2026-07-22', 'close' => 105.32],
            ]),
        ]);

        $provider = $this->provider();
        $history = $provider->fetchHistory('VWCE', 'XETRA', Carbon::parse('2026-01-01'), Carbon::parse('2026-07-23'));

        $this->assertNotNull($history);
        $this->assertCount(3, $history);
        $this->assertSame(100.0, $history[0]->price);
        $this->assertSame('2026-07-20', $history[0]->date->format('Y-m-d'));
        $this->assertSame(105.32, $history[2]->price);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'eod/VWCE.XETRA')
                && $request['from'] === '2026-01-01'
                && $request['to'] === '2026-07-23';
        });
    }

    public function test_it_returns_an_empty_array_when_the_history_call_succeeds_with_no_data()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([]),
        ]);

        $provider = $this->provider();
        $history = $provider->fetchHistory('VWCE', 'XETRA', Carbon::parse('2026-01-01'), Carbon::parse('2026-07-23'));

        $this->assertSame([], $history);
    }

    public function test_it_returns_null_when_the_history_request_fails()
    {
        Http::fake([
            'eodhd.com/api/eod/*' => Http::response(null, 500),
        ]);

        $provider = $this->provider();

        $this->assertNull($provider->fetchHistory('VWCE', 'XETRA', Carbon::parse('2026-01-01'), Carbon::parse('2026-07-23')));
    }

    public function test_it_fetches_news_for_a_symbol()
    {
        Http::fake([
            'eodhd.com/api/news*' => Http::response([
                [
                    'date' => '2026-07-24T09:00:00+00:00',
                    'title' => 'Apple reports strong quarterly earnings',
                    'content' => 'Full article body...',
                    'link' => 'https://example.com/apple-earnings',
                    'tags' => ['earnings', 'technology'],
                    'sentiment' => ['polarity' => 0.42, 'neg' => 0.05, 'neu' => 0.6, 'pos' => 0.35],
                ],
            ]),
        ]);

        $provider = $this->provider();
        $news = $provider->fetchNews('AAPL', 'US');

        $this->assertNotNull($news);
        $this->assertCount(1, $news);
        $this->assertSame('Apple reports strong quarterly earnings', $news[0]->title);
        $this->assertSame('Full article body...', $news[0]->content);
        $this->assertSame('https://example.com/apple-earnings', $news[0]->url);
        $this->assertSame('2026-07-24', $news[0]->publishedAt->format('Y-m-d'));
        $this->assertSame(['earnings', 'technology'], $news[0]->tags);
        $this->assertSame(0.42, $news[0]->sentimentPolarity);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'news') && $request['s'] === 'AAPL.US');
    }

    public function test_it_returns_an_empty_array_when_the_news_call_succeeds_with_no_articles()
    {
        Http::fake([
            'eodhd.com/api/news*' => Http::response([]),
        ]);

        $provider = $this->provider();

        $this->assertSame([], $provider->fetchNews('AAPL', 'US'));
    }

    public function test_it_returns_null_when_the_news_request_fails()
    {
        Http::fake([
            'eodhd.com/api/news*' => Http::response(null, 500),
        ]);

        $provider = $this->provider();

        $this->assertNull($provider->fetchNews('AAPL', 'US'));
    }

    public function test_it_refuses_to_fetch_news_when_the_budget_is_below_the_news_call_cost()
    {
        Http::fake();

        // Below the news endpoint's real cost (10) despite being enough for
        // any other single call - the budget check must use the endpoint's
        // own cost, not an implicit ">= 1".
        $provider = new EodhdMarketPriceProvider('fake-token', new EodhdCallBudget(dailyLimit: 9));

        $this->assertNull($provider->fetchNews('AAPL', 'US'));
        Http::assertNothingSent();
    }
}
