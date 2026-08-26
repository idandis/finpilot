<?php

namespace Tests\Feature\Finance;

use App\Models\MacroIndicatorObservation;
use App\Models\MarketOverviewPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MacroControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_refresh_button_updates_macro_indicators_and_redirects_back()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.stlouisfed.org/*' => Http::response([
                'observations' => [['date' => '2026-06-01', 'value' => '2.8']],
            ]),
            'data-api.ecb.europa.eu/*' => Http::response("TIME_PERIOD,OBS_VALUE\n2026-06,3.1\n"),
        ]);

        $response = $this->actingAs($user)->from(route('macro.index'))->post(route('macro.refresh'));

        $response->assertRedirect(route('macro.index'));
        $this->assertGreaterThan(0, MacroIndicatorObservation::count());
    }

    public function test_the_markets_refresh_button_updates_market_instruments_and_redirects_back()
    {
        $user = User::factory()->create();

        Http::fake([
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-30', 'close' => 5252.0],
            ]),
        ]);

        $response = $this->actingAs($user)->from(route('macro.index'))->post(route('macro.markets.refresh'));

        $response->assertRedirect(route('macro.index'));
        $this->assertGreaterThan(0, MarketOverviewPriceHistory::count());
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('macro.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_renders_the_macro_page_grouped_by_category()
    {
        $user = User::factory()->create();

        MacroIndicatorObservation::create([
            'indicator_key' => 'us_cpi_yoy',
            'observation_date' => '2026-05-31',
            'value' => 3.0,
        ]);
        MacroIndicatorObservation::create([
            'indicator_key' => 'us_cpi_yoy',
            'observation_date' => '2026-06-30',
            'value' => 2.8,
        ]);

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            ->has('categories')
            ->where('categories.0.key', 'inflazione')
            ->where('categories.0.indicators.0.key', 'us_cpi_yoy')
            ->where('categories.0.indicators.0.current.value', 2.8)
            ->where('categories.0.indicators.0.previous.value', 3)
            ->where('categories.0.indicators.0.trend', 'improving')
            ->has('categories.0.indicators.0.narrative')
            ->has('markets')
        );
    }

    public function test_it_renders_market_overview_grouped_by_category()
    {
        $user = User::factory()->create();

        MarketOverviewPriceHistory::create([
            'instrument_key' => 'sp500',
            'price_date' => '2026-07-29',
            'close_price' => 5200.0,
        ]);
        MarketOverviewPriceHistory::create([
            'instrument_key' => 'sp500',
            'price_date' => '2026-07-30',
            'close_price' => 5252.0,
        ]);

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            ->where('markets.0.key', 'indici')
            ->where('markets.0.instruments.0.key', 'sp500')
            ->where('markets.0.instruments.0.current.value', 5252)
            ->where('markets.0.instruments.0.day_change_percent', 1)
        );
    }

    public function test_sectors_are_split_out_of_markets_into_their_own_flat_list()
    {
        $user = User::factory()->create();

        MarketOverviewPriceHistory::create([
            'instrument_key' => 'sector_technology',
            'price_date' => '2026-07-30',
            'close_price' => 100.0,
        ]);

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            ->has('sectors', 11)
            ->where('sectors.0.key', 'sector_technology')
            // 'settori' is excluded from the grouped 'markets' list - only
            // the other 5 categories (indici, volatilita, commodity,
            // valute, crypto) remain there.
            ->has('markets', 5)
        );
    }

    public function test_it_exposes_a_regime_classification_and_risk_sentiment_reflecting_seeded_data()
    {
        $user = User::factory()->create();

        MacroIndicatorObservation::create(['indicator_key' => 'us_gdp_growth', 'observation_date' => '2026-03-31', 'value' => 3.1]);
        MacroIndicatorObservation::create(['indicator_key' => 'us_gdp_growth', 'observation_date' => '2026-06-30', 'value' => 3.0]);
        MacroIndicatorObservation::create(['indicator_key' => 'us_cpi_yoy', 'observation_date' => '2026-05-31', 'value' => 2.1]);
        MacroIndicatorObservation::create(['indicator_key' => 'us_cpi_yoy', 'observation_date' => '2026-06-30', 'value' => 2.0]);
        MacroIndicatorObservation::create(['indicator_key' => 'us_unemployment', 'observation_date' => '2026-05-31', 'value' => 3.9]);
        MacroIndicatorObservation::create(['indicator_key' => 'us_unemployment', 'observation_date' => '2026-06-30', 'value' => 3.8]);

        MarketOverviewPriceHistory::create(['instrument_key' => 'sp500', 'price_date' => '2026-07-29', 'close_price' => 5200.0]);
        MarketOverviewPriceHistory::create(['instrument_key' => 'sp500', 'price_date' => '2026-07-30', 'close_price' => 5252.0]);

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            // Strong growth + low inflation + low unemployment.
            ->where('regime.key', 'non_inflationary_growth')
            ->has('regime.confidence_percent')
            ->has('regime.signals')
            // S&P 500 up on the day is the only signal with data - a lone
            // positive signal and nothing else.
            ->where('riskSentiment.positive_signals', ['Azioni in crescita'])
        );
    }

    public function test_instruments_without_data_yet_show_a_placeholder_instead_of_being_hidden()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            ->has('markets.0.instruments.0', fn ($instrument) => $instrument
                ->where('current', null)
                ->where('trend', 'neutral')
                ->etc()
            )
        );
    }

    public function test_indicators_without_data_yet_show_a_placeholder_instead_of_being_hidden()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('macro.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Macro/Index')
            ->has('categories.0.indicators.0', fn ($indicator) => $indicator
                ->where('current', null)
                ->where('narrative', 'Dato non ancora disponibile.')
                ->etc()
            )
        );
    }
}
