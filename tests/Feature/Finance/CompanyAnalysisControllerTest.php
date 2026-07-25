<?php

namespace Tests\Feature\Finance;

use App\Contracts\FundamentalDataProvider;
use App\Contracts\MarketPriceProvider;
use App\Models\CompanyAnalysis;
use App\Models\CompanyAnalysisPriceHistory;
use App\Models\User;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\EodhdMarketPriceProvider;
use App\Services\Finance\FmpFundamentalDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyAnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $analysis = CompanyAnalysis::factory()->create();

        $this->get(route('company-analyses.index'))->assertRedirect(route('login'));
        $this->get(route('company-analyses.show', $analysis))->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_users_own_analyses()
    {
        $user = User::factory()->create();
        $mine = CompanyAnalysis::factory()->for($user)->create(['name' => 'Apple Inc.']);
        CompanyAnalysis::factory()->create(['name' => 'Someone else']);

        $response = $this->actingAs($user)->get(route('company-analyses.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('analyses', 1)
            ->where('analyses.0.id', $mine->id)
            ->where('analyses.0.name', 'Apple Inc.')
        );
    }

    public function test_a_user_can_create_a_new_analysis()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company-analyses.store'), [
            'name' => 'Apple Inc.',
            'symbol' => 'aapl.us',
        ]);

        $analysis = CompanyAnalysis::first();
        $response->assertRedirect(route('company-analyses.show', $analysis));

        $this->assertSame($user->id, $analysis->user_id);
        $this->assertSame('Apple Inc.', $analysis->name);
        // Uppercased for consistency with EODHD's own symbol casing.
        $this->assertSame('AAPL.US', $analysis->symbol);
    }

    public function test_creating_an_analysis_requires_a_name_and_symbol()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('company-analyses.store'), []);

        $response->assertSessionHasErrors(['name', 'symbol']);
    }

    public function test_a_user_cannot_view_another_users_analysis()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->create();

        $this->actingAs($user)->get(route('company-analyses.show', $analysis))->assertForbidden();
    }

    public function test_show_merges_stored_answers_with_the_full_fixed_question_list()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create([
            'buffett_answers' => [
                ['key' => 'understand_business', 'answer' => true, 'notes' => 'Sì, modello pubblicitario chiaro'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('buffettQuestions', 10)
            ->where('buffettQuestions.0.key', 'understand_business')
            ->where('buffettQuestions.0.answer', true)
            ->where('buffettQuestions.0.notes', 'Sì, modello pubblicitario chiaro')
            ->where('buffettQuestions.1.answer', null)
            ->where('buffettQuestions.1.notes', null)
        );
    }

    public function test_a_user_can_manually_update_the_financial_indicators()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('company-analyses.update', $analysis), [
            'revenue_growth' => 15.7,
            'pe_ratio' => 28.5,
        ]);

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertSame(15.7, (float) $analysis->revenue_growth);
        $this->assertSame(28.5, (float) $analysis->pe_ratio);
    }

    public function test_a_user_can_manually_update_the_extended_indicators()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('company-analyses.update', $analysis), [
            'net_margin' => 24.5,
            'gross_margin' => 45.2,
            'revenue_cagr_5y' => 12.1,
            'eps_cagr_5y' => 15.6,
            'interest_coverage' => 14.2,
            'current_ratio' => 1.6,
            'fcf_margin' => 18.3,
            'ev_to_fcf' => 26.4,
            'price_to_sales' => 8.1,
        ]);

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertSame(24.5, (float) $analysis->net_margin);
        $this->assertSame(45.2, (float) $analysis->gross_margin);
        $this->assertSame(12.1, (float) $analysis->revenue_cagr_5y);
        $this->assertSame(15.6, (float) $analysis->eps_cagr_5y);
        $this->assertSame(14.2, (float) $analysis->interest_coverage);
        $this->assertSame(1.6, (float) $analysis->current_ratio);
        $this->assertSame(18.3, (float) $analysis->fcf_margin);
        $this->assertSame(26.4, (float) $analysis->ev_to_fcf);
        $this->assertSame(8.1, (float) $analysis->price_to_sales);
    }

    public function test_show_includes_a_1_to_10_score_for_each_scoreable_indicator()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create([
            'revenue_growth' => 20.0,
            'debt_to_ebitda' => 0.5,
        ]);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('analysis.scores.revenue_growth', 10)
            ->where('analysis.scores.debt_to_ebitda', 10)
            ->where('analysis.scores.roe', null)
        );
    }

    public function test_show_includes_the_valuation_verdict_from_current_price_and_fair_value()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create([
            'current_price' => 532.0,
            'fair_value' => 515.0,
        ]);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('analysis.current_price', fn ($value) => (float) $value === 532.0)
            ->where('analysis.valuation.verdict', 'fair')
            ->where('analysis.valuation.recommended_action', 'Accumula gradualmente')
            ->where('analysis.valuation.entry_price', 489.25)
            ->where('analysis.valuation.accumulate_price', 437.75)
        );
    }

    public function test_show_has_a_null_valuation_when_fair_value_is_missing()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['current_price' => 532.0]);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('analysis.valuation.verdict', null)
            ->where('analysis.valuation.entry_price', null)
        );
    }

    public function test_a_user_can_manually_update_the_fase_3_valuation_fields()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('company-analyses.update', $analysis), [
            'fcf_yield' => 5.2,
            'fair_value' => 420.50,
            'historical_comparison' => 'P/E medio 10 anni: 30, oggi: 45',
            'competitor_comparison' => 'Più cara di Oracle e Salesforce',
        ]);

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertSame(5.2, (float) $analysis->fcf_yield);
        $this->assertSame(420.50, (float) $analysis->fair_value);
        $this->assertSame('P/E medio 10 anni: 30, oggi: 45', $analysis->historical_comparison);
        $this->assertSame('Più cara di Oracle e Salesforce', $analysis->competitor_comparison);
    }

    public function test_a_user_can_save_buffett_answers()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('company-analyses.update', $analysis), [
            'buffett_answers' => [
                ['key' => 'understand_business', 'answer' => true, 'notes' => 'Sì'],
                ['key' => 'low_debt', 'answer' => false, 'notes' => null],
            ],
        ]);

        $response->assertRedirect();
        $analysis->refresh();

        $stored = collect($analysis->buffett_answers)->keyBy('key');
        $this->assertTrue($stored->get('understand_business')['answer']);
        $this->assertFalse($stored->get('low_debt')['answer']);
    }

    public function test_a_user_cannot_update_another_users_analysis()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->create();

        $this->actingAs($user)
            ->patch(route('company-analyses.update', $analysis), ['pe_ratio' => 10])
            ->assertForbidden();
    }

    public function test_refresh_indicators_maps_the_fetched_fundamentals_onto_the_analysis()
    {
        $this->app->bind(FundamentalDataProvider::class, fn () => new FmpFundamentalDataProvider('fake-key'));

        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([['currency' => 'USD', 'price' => 428.5, 'marketCap' => 3186000000000]]),
            'financialmodelingprep.com/stable/ratios-ttm*' => Http::response([['priceToEarningsRatioTTM' => 28.5]]),
            'financialmodelingprep.com/stable/financial-growth*' => Http::response([['revenueGrowth' => 0.157]]),
            'financialmodelingprep.com/stable/key-metrics-ttm*' => Http::response([[]]),
            'financialmodelingprep.com/stable/cash-flow-statement*' => Http::response([[]]),
        ]);

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'AAPL.US']);

        $response = $this->actingAs($user)->post(route('company-analyses.refresh-indicators', $analysis));

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertSame(15.7, (float) $analysis->revenue_growth);
        $this->assertSame(28.5, (float) $analysis->pe_ratio);
        $this->assertSame('USD', $analysis->indicators_currency);
        $this->assertSame(428.5, (float) $analysis->current_price);
        $this->assertSame(3186000000000.0, (float) $analysis->market_cap);
        $this->assertNotNull($analysis->indicators_fetched_at);
    }

    public function test_refresh_indicators_leaves_existing_values_untouched_when_the_fetch_fails()
    {
        $this->app->bind(FundamentalDataProvider::class, fn () => new FmpFundamentalDataProvider('fake-key'));

        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response(null, 500),
        ]);

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['pe_ratio' => 20.0]);

        $response = $this->actingAs($user)->post(route('company-analyses.refresh-indicators', $analysis));

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertSame(20.0, (float) $analysis->pe_ratio);
        $this->assertNull($analysis->indicators_fetched_at);
    }

    public function test_refresh_indicators_fails_when_the_profile_resolves_but_no_fundamentals_exist()
    {
        $this->app->bind(FundamentalDataProvider::class, fn () => new FmpFundamentalDataProvider('fake-key'));

        // A private company's ticker can still resolve a stray, unrelated
        // profile on FMP (with a price) while every actual fundamentals
        // endpoint comes back empty - this must not be treated as success.
        Http::fake([
            'financialmodelingprep.com/stable/profile*' => Http::response([['currency' => 'USD', 'price' => 112.96]]),
            'financialmodelingprep.com/stable/ratios-ttm*' => Http::response([]),
            'financialmodelingprep.com/stable/key-metrics-ttm*' => Http::response([]),
            'financialmodelingprep.com/stable/financial-growth*' => Http::response([]),
            'financialmodelingprep.com/stable/cash-flow-statement*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'SPCX']);

        $response = $this->actingAs($user)->post(route('company-analyses.refresh-indicators', $analysis));

        $response->assertRedirect();
        $analysis->refresh();
        $this->assertNull($analysis->current_price);
        $this->assertNull($analysis->pe_ratio);
        $this->assertNull($analysis->indicators_fetched_at);
    }

    public function test_refresh_indicators_does_nothing_without_an_fmp_api_key()
    {
        $this->app->bind(FundamentalDataProvider::class, fn () => new FmpFundamentalDataProvider(null));
        Http::fake();

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $this->actingAs($user)->post(route('company-analyses.refresh-indicators', $analysis));

        Http::assertNothingSent();
    }

    public function test_a_user_cannot_refresh_another_users_analysis()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->create();

        $this->actingAs($user)
            ->post(route('company-analyses.refresh-indicators', $analysis))
            ->assertForbidden();
    }

    public function test_show_includes_the_cached_price_history_as_candles()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'MSFT.US']);

        CompanyAnalysisPriceHistory::factory()->create([
            'symbol' => 'MSFT.US',
            'price_date' => '2026-07-20',
            'close_price' => 400.25,
            'open_price' => 398.5,
            'high_price' => 402.0,
            'low_price' => 397.0,
        ]);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('priceHistory.0.time', '2026-07-20')
            ->where('priceHistory.0.close', 400.25)
            ->where('priceHistory.0.open', 398.5)
        );
    }

    public function test_show_includes_a_technical_analysis_reading_computed_from_the_price_history()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'MSFT.US']);

        // 25 strictly increasing closes so sma20/rsi14 are both defined and
        // the "above/overbought" signals are deterministic - same fixture
        // shape already used in TechnicalIndicatorsTest.
        for ($i = 100; $i < 125; $i++) {
            CompanyAnalysisPriceHistory::factory()->create([
                'symbol' => 'MSFT.US',
                'price_date' => sprintf('2026-01-%02d', ($i - 99)),
                'close_price' => $i,
                'open_price' => $i,
                'high_price' => $i,
                'low_price' => $i,
            ]);
        }

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('technicalAnalysis.sma20', fn ($value) => (float) $value === 114.5)
            ->where('technicalAnalysis.sma20_signal', 'above')
            ->where('technicalAnalysis.rsi14', fn ($value) => (float) $value === 100.0)
            ->where('technicalAnalysis.rsi14_signal', 'overbought')
        );
    }

    public function test_show_has_a_null_technical_analysis_when_there_is_no_price_history()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'MSFT.US']);

        $response = $this->actingAs($user)->get(route('company-analyses.show', $analysis));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('technicalAnalysis.sma20', null)
            ->where('technicalAnalysis.rsi14', null)
        );
    }

    public function test_refresh_price_history_fetches_and_caches_ohlc_candles()
    {
        $this->app->bind(MarketPriceProvider::class, fn () => new EodhdMarketPriceProvider('fake-key', new EodhdCallBudget(100)));

        Http::fake([
            'eodhd.com/api/eod/MSFT.US*' => Http::response([
                ['date' => '2026-07-20', 'open' => 398.0, 'high' => 402.0, 'low' => 397.0, 'close' => 400.0],
                ['date' => '2026-07-21', 'open' => 400.0, 'high' => 406.0, 'low' => 399.0, 'close' => 405.5],
            ]),
        ]);

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => null]);

        $response = $this->actingAs($user)->post(route('company-analyses.refresh-price-history', $analysis));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast.type', 'success');
        $analysis->refresh();
        $this->assertNotNull($analysis->price_history_fetched_at);
        $this->assertDatabaseCount('company_analysis_price_history', 2);
    }

    public function test_refresh_price_history_shows_an_error_toast_when_the_provider_call_fails()
    {
        $this->app->bind(MarketPriceProvider::class, fn () => new EodhdMarketPriceProvider('fake-key', new EodhdCallBudget(100)));

        Http::fake([
            'eodhd.com/api/eod/MSFT.US*' => Http::response(null, 500),
        ]);

        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create(['symbol' => 'MSFT.US', 'price_history_fetched_at' => null]);

        $response = $this->actingAs($user)->post(route('company-analyses.refresh-price-history', $analysis));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast.type', 'error');
        $this->assertNull($analysis->fresh()->price_history_fetched_at);
    }

    public function test_a_user_cannot_refresh_another_users_price_history()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->create();

        $this->actingAs($user)
            ->post(route('company-analyses.refresh-price-history', $analysis))
            ->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_analysis()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('company-analyses.destroy', $analysis));

        $response->assertRedirect(route('company-analyses.index'));
        $this->assertDatabaseMissing('company_analyses', ['id' => $analysis->id]);
    }

    public function test_a_user_cannot_delete_another_users_analysis()
    {
        $user = User::factory()->create();
        $analysis = CompanyAnalysis::factory()->create();

        $this->actingAs($user)->delete(route('company-analyses.destroy', $analysis))->assertForbidden();
        $this->assertDatabaseHas('company_analyses', ['id' => $analysis->id]);
    }
}
