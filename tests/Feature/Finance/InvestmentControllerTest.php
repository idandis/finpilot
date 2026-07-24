<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\InstrumentPrice;
use App\Models\InstrumentPriceHistory;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class InvestmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('investments.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_shows_versato_and_rientrato_for_the_investimenti_category_only()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);
        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        // A buy trade: cash goes out.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 200,
        ]);
        // A sell trade: cash comes back.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-10',
            'direction' => 'income',
            'amount' => 50,
        ]);
        // An unrelated grocery expense must not count towards the investment flow.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $groceries->id,
            'transaction_date' => '2026-07-12',
            'direction' => 'expense',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.id', 'all')
            ->where('tabs.0.cashFlow.0.year', 2026)
            ->where('tabs.0.cashFlow.0.totals.versato', 200)
            ->where('tabs.0.cashFlow.0.totals.rientrato', 50)
            ->has('tabs.0.cashFlow.0.months', 12)
            // July is month index 6 (0-based) in the 1..12 range.
            ->where('tabs.0.cashFlow.0.months.6.versato', 200)
            ->where('tabs.0.cashFlow.0.months.6.rientrato', 50)
        );
    }

    public function test_it_adds_one_tab_per_card_scoped_to_that_cards_own_investment_flow()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $cardOne = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Trade Republic', 'is_investment_card' => true]);
        $cardTwo = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Altra carta', 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $cardOne->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 150,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs', 3)
            ->where('tabs.0.cashFlow.0.totals.versato', 150)
            ->where('tabs.1.name', 'Altra carta')
            ->where('tabs.1.cashFlow.0.totals.versato', 0)
            ->where('tabs.2.name', 'Trade Republic')
            ->where('tabs.2.cashFlow.0.totals.versato', 150)
        );
    }

    public function test_it_excludes_cards_not_flagged_as_investment_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Trade Republic', 'is_investment_card' => true]);
        $regularCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta di debito', 'is_investment_card' => false]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 150,
        ]);
        // Belongs to a non-investment card - must not produce a tab, and
        // must not count towards the aggregate "all cards" tab either.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $regularCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs', 2)
            ->where('tabs.0.cashFlow.0.totals.versato', 150)
            ->where('tabs.1.name', 'Trade Republic')
            ->where('tabs.1.cashFlow.0.totals.versato', 150)
        );
    }

    public function test_it_reports_an_open_position_after_a_buy()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard Funds PLC - Vanguard FTSE All-World UCITS ETF (USD) Accumulating, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.positions.open', 1)
            ->where('tabs.0.positions.open.0.isin', 'IE00BK5BQT80')
            ->where('tabs.0.positions.open.0.quantity', 2)
            ->where('tabs.0.positions.open.0.invested', 200)
            ->where('tabs.0.positions.open.0.average_price', 100)
            ->has('tabs.0.positions.closed', 0)
            ->where('tabs.0.positions.open.0.current_price', null)
            ->where('tabs.0.positions.open.0.market_value', null)
            ->where('tabs.0.positions.open.0.unrealized_gain', null)
        );
    }

    public function test_it_shows_market_value_and_unrealized_gain_when_a_price_is_cached()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'last_price' => 120,
            'currency' => 'EUR',
            'price_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.positions.open.0.current_price', 120)
            ->where('tabs.0.positions.open.0.market_value', 240)
            ->where('tabs.0.positions.open.0.unrealized_gain', 40)
            ->where('tabs.0.positions.open.0.unrealized_gain_percent', 20)
        );
    }

    public function test_it_converts_a_non_eur_price_using_the_cached_exchange_rate()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade US0378331005 Apple Inc, quantity: 2.0',
            'isin' => 'US0378331005',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'last_price' => 230,
            'currency' => 'USD',
            'price_date' => '2026-07-22',
        ]);

        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.9,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.positions.open.0.current_price', 207)
            ->where('tabs.0.positions.open.0.market_value', 414)
            ->where('tabs.0.positions.open.0.price_currency', 'USD')
            ->where('tabs.0.positions.open.0.current_price_original', 230)
            ->where('tabs.0.positions.open.0.market_value_original', 460)
        );
    }

    public function test_it_does_not_convert_a_non_eur_price_without_a_cached_exchange_rate()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade US0378331005 Apple Inc, quantity: 2.0',
            'isin' => 'US0378331005',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'last_price' => 230,
            'currency' => 'USD',
            'price_date' => '2026-07-22',
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.positions.open.0.current_price', null)
            ->where('tabs.0.positions.open.0.market_value', null)
            ->where('tabs.0.positions.open.0.price_currency', 'USD')
        );
    }

    public function test_it_reports_a_closed_position_with_realized_gain_after_a_full_sell()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-10',
            'description' => 'Sell trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'income',
            'amount' => 210,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.positions.open', 0)
            ->has('tabs.0.positions.closed', 1)
            ->where('tabs.0.positions.closed.0.isin', 'IT0005495657')
            ->where('tabs.0.positions.closed.0.invested', 200)
            ->where('tabs.0.positions.closed.0.received', 210)
            ->where('tabs.0.positions.closed.0.realized_gain', 10)
        );
    }

    public function test_it_reports_realized_gain_from_a_partial_sell_on_a_still_open_position()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IT0005495657 SAIPEM, quantity: 100.0',
            'isin' => 'IT0005495657',
            'quantity' => 100.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-10',
            'description' => 'Sell trade IT0005495657 SAIPEM, quantity: 40.0',
            'isin' => 'IT0005495657',
            'quantity' => 40.0,
            'direction' => 'income',
            'amount' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.positions.open', 1)
            ->has('tabs.0.positions.closed', 0)
            ->where('tabs.0.positions.open.0.isin', 'IT0005495657')
            ->where('tabs.0.positions.open.0.quantity', 60)
            ->where('tabs.0.positions.open.0.invested', 120)
            ->where('tabs.0.positions.open.0.realized_gain', 20)
        );
    }

    public function test_it_closes_a_position_when_a_buy_trade_is_fully_cancelled()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade US84615Q1031 SPACE EXPL.TECHS. CL.A, quantity: 1.078399',
            'isin' => 'US84615Q1031',
            'quantity' => 1.078399,
            'direction' => 'expense',
            'amount' => 500,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-06',
            'description' => 'Cancellation Buy trade US84615Q1031 SPACE EXPL.TECHS. CL.A, quantity: 1.078399',
            'isin' => 'US84615Q1031',
            'quantity' => 1.078399,
            'direction' => 'income',
            'amount' => 500,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.positions.open', 0)
            ->has('tabs.0.positions.closed', 1)
            ->where('tabs.0.positions.closed.0.isin', 'US84615Q1031')
            ->where('tabs.0.positions.closed.0.invested', 500)
            ->where('tabs.0.positions.closed.0.received', 500)
            ->where('tabs.0.positions.closed.0.realized_gain', 0)
        );
    }

    /**
     * Regression test for a real bug: extracting an ISIN from "Cash
     * Dividend for ISIN ..." rows (added so investment-category dividends
     * carry their instrument) must NOT make the position calculator treat
     * the dividend as a buy - it has no quantity, so it would silently
     * inflate the average cost of an existing open position otherwise.
     */
    public function test_dividend_transactions_do_not_pollute_open_position_cost_basis()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade XF000ETH0019 Ethereum, quantity: 2.0',
            'isin' => 'XF000ETH0019',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-10',
            'description' => 'Cash Dividend for ISIN XF000ETH0019',
            'isin' => 'XF000ETH0019',
            'quantity' => null,
            'direction' => 'income',
            'amount' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.positions.open', 1)
            ->where('tabs.0.positions.open.0.isin', 'XF000ETH0019')
            ->where('tabs.0.positions.open.0.quantity', 2)
            ->where('tabs.0.positions.open.0.invested', 200)
            ->where('tabs.0.positions.open.0.average_price', 100)
            ->has('tabs.0.positions.closed', 0)
        );
    }

    public function test_it_reports_cumulative_invested_capital_over_time()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'direction' => 'expense',
            'amount' => 200,
        ]);
        // A later full sell must not erase the capital that was invested
        // earlier - the "invested" line is cumulative net cash flow, not
        // the cost basis of positions still open today.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-03-16',
            'direction' => 'income',
            'amount' => 220,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.portfolioHistory.points', function (Collection $points) {
                $midPoint = $points->first(fn ($point) => $point['date'] >= '2026-02-01');
                $this->assertNotNull($midPoint);
                $this->assertEquals(200.0, $midPoint['invested']);

                $lastPoint = $points->last();
                $this->assertEquals(-20.0, $lastPoint['invested']);

                return true;
            })
        );
    }

    public function test_it_reports_market_value_using_historical_prices_when_available()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create(['isin' => 'IE00BK5BQT80', 'currency' => 'EUR']);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-01-05', 'close_price' => 100.0]);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-03-01', 'close_price' => 120.0]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.portfolioHistory.points', function (Collection $points) {
                $marchPoint = $points->first(fn ($point) => $point['date'] >= '2026-03-01');
                $this->assertNotNull($marchPoint);
                $this->assertEquals(240.0, $marchPoint['market_value']);

                return true;
            })
        );
    }

    public function test_it_excludes_an_isin_from_market_value_when_its_price_history_does_not_cover_that_date()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create(['isin' => 'IE00BK5BQT80', 'currency' => 'EUR']);
        // Price history only starts in April - well after the purchase.
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-04-01', 'close_price' => 120.0]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.portfolioHistory.points', function (Collection $points) {
                $februaryPoint = $points->first(fn ($point) => $point['date'] >= '2026-02-01' && $point['date'] < '2026-03-01');
                $this->assertNotNull($februaryPoint);
                $this->assertNull($februaryPoint['market_value']);

                $mayPoint = $points->first(fn ($point) => $point['date'] >= '2026-05-01');
                $this->assertNotNull($mayPoint);
                $this->assertEquals(240.0, $mayPoint['market_value']);

                return true;
            })
        );
    }

    public function test_it_reports_market_data_since_when_price_history_starts_after_the_first_purchase()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create(['isin' => 'IE00BK5BQT80', 'currency' => 'EUR']);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-04-06', 'close_price' => 120.0]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.portfolioHistory.market_data_since', function (?string $marketDataSince) {
                $this->assertNotNull($marketDataSince);
                $this->assertGreaterThanOrEqual('2026-04-06', $marketDataSince);

                return true;
            })
        );
    }

    public function test_portfolio_history_is_empty_when_the_tab_has_no_investment_transactions()
    {
        $user = User::factory()->create();
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.portfolioHistory.points', 0)
            ->where('tabs.0.portfolioHistory.market_data_since', null)
            ->has('tabs.0.portfolioHistory.unpriced_positions', 0)
        );
    }

    public function test_it_reports_a_currently_held_position_with_no_price_history_as_unpriced()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        // A crypto-style ISIN EODHD has already tried and failed to resolve
        // (resolution_failed=true, set by a previous refresh run) - it will
        // never get a price, and its own cash flow must be excluded from
        // "invested" too, so both lines compare the same priceable subset.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade XF000BTC0017 Bitcoin, quantity: 0.01',
            'isin' => 'XF000BTC0017',
            'quantity' => 0.01,
            'direction' => 'expense',
            'amount' => 300,
        ]);
        InstrumentPrice::factory()->create([
            'isin' => 'XF000BTC0017',
            'code' => null,
            'exchange' => null,
            'currency' => null,
            'resolution_failed' => true,
        ]);

        // A normal, priced instrument held alongside it - must NOT show up
        // in unpriced_positions, and must be the only one counted in "invested".
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create(['isin' => 'IE00BK5BQT80', 'currency' => 'EUR']);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-01-05', 'close_price' => 100.0]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tabs.0.portfolioHistory.unpriced_positions', 1)
            ->where('tabs.0.portfolioHistory.unpriced_positions.0.isin', 'XF000BTC0017')
            ->where('tabs.0.portfolioHistory.unpriced_positions.0.name', 'Bitcoin')
            ->where('tabs.0.portfolioHistory.unpriced_positions.0.quantity', 0.01)
            ->where('tabs.0.portfolioHistory.unpriced_positions.0.invested', 300)
            ->where('tabs.0.portfolioHistory.points', fn (Collection $points) => $points->last()['invested'] === 200)
        );
    }

    public function test_it_flags_crypto_positions_and_sorts_them_to_the_bottom()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        // Invested far more than the stock below, to prove the sort truly
        // pins crypto to the bottom rather than coincidentally landing there
        // via the normal "invested" descending order.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade XF000BTC0017 Bitcoin, quantity: 0.01',
            'isin' => 'XF000BTC0017',
            'quantity' => 0.01,
            'direction' => 'expense',
            'amount' => 500,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.positions.open.0.isin', 'IE00BK5BQT80')
            ->where('tabs.0.positions.open.0.is_crypto', false)
            ->where('tabs.0.positions.open.1.isin', 'XF000BTC0017')
            ->where('tabs.0.positions.open.1.is_crypto', true)
        );
    }

    public function test_it_flags_crypto_closed_positions_and_sorts_them_to_the_bottom()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        // Closed more recently than the stock below, to prove the sort
        // truly pins crypto to the bottom rather than coincidentally
        // landing there via the normal "closed_at" descending order.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade XF000BTC0017 Bitcoin, quantity: 0.01',
            'isin' => 'XF000BTC0017',
            'quantity' => 0.01,
            'direction' => 'expense',
            'amount' => 300,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-02-01',
            'description' => 'Sell trade XF000BTC0017 Bitcoin, quantity: 0.01',
            'isin' => 'XF000BTC0017',
            'quantity' => 0.01,
            'direction' => 'income',
            'amount' => 310,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-05',
            'description' => 'Buy trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-10',
            'description' => 'Sell trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'income',
            'amount' => 210,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.positions.closed.0.isin', 'IT0005495657')
            ->where('tabs.0.positions.closed.0.is_crypto', false)
            ->where('tabs.0.positions.closed.1.isin', 'XF000BTC0017')
            ->where('tabs.0.positions.closed.1.is_crypto', true)
        );
    }

    public function test_it_does_not_include_another_users_transactions()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($otherAccount, 'financialAccount')->create([
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.cashFlow.0.totals.versato', 0)
            ->where('tabs.0.cashFlow.0.totals.rientrato', 0)
        );
    }

    public function test_it_reports_the_account_balance_as_initial_balance_plus_transactions()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.accountBalance', 800)
            ->where('tabs.1.accountBalance', 800)
        );
    }

    public function test_it_reports_wealth_history_combining_market_value_and_account_balance()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create(['isin' => 'IE00BK5BQT80', 'currency' => 'EUR']);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-01-05', 'close_price' => 100.0]);
        InstrumentPriceHistory::factory()->create(['isin' => 'IE00BK5BQT80', 'price_date' => '2026-03-01', 'close_price' => 120.0]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.wealthHistory.points', function (Collection $points) {
                $marchPoint = $points->first(fn ($point) => $point['date'] >= '2026-03-01');
                $this->assertNotNull($marchPoint);
                // Account balance as of that date: 1000 initial - 200 buy = 800.
                $this->assertEquals(800.0, $marchPoint['invested']);
                // Net worth: 240 market value (2 x 120) + 800 account balance.
                $this->assertEquals(1040.0, $marchPoint['market_value']);

                return true;
            })
        );
    }

    public function test_it_reports_a_null_wealth_history_when_no_account_is_linked()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true, 'financial_account_id' => null]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.wealthHistory', null)
            ->where('tabs.1.wealthHistory', null)
        );
    }

    public function test_it_includes_a_non_investment_cards_transactions_in_the_shared_account_balance()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 500]);
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $debitCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 1.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 1.0,
            'direction' => 'expense',
            'amount' => 100,
        ]);

        // An everyday grocery expense on a debit card sharing the same
        // account - not an investment transaction, but it still spends real
        // cash out of the same balance and must be reflected in it.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $debitCard->id,
            'transaction_date' => '2026-07-06',
            'direction' => 'expense',
            'amount' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.accountBalance', 350)
        );
    }

    public function test_it_reports_a_null_account_balance_when_the_card_has_no_linked_account()
    {
        $user = User::factory()->create();
        Card::factory()->create(['user_id' => $user->id, 'is_investment_card' => true, 'financial_account_id' => null]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.accountBalance', null)
            ->where('tabs.1.accountBalance', null)
        );
    }

    public function test_the_all_tab_sums_account_balances_across_distinct_accounts()
    {
        $user = User::factory()->create();
        $accountOne = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $accountTwo = FinancialAccount::factory()->for($user)->create(['initial_balance' => 2000]);
        Card::factory()->for($accountOne, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        Card::factory()->for($accountTwo, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $response = $this->actingAs($user)->get(route('investments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tabs.0.accountBalance', 3000)
        );
    }

    public function test_a_user_can_manually_trigger_a_price_refresh()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('investments.refresh'));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast');
    }

    public function test_guests_cannot_trigger_a_price_refresh()
    {
        $response = $this->post(route('investments.refresh'));

        $response->assertRedirect(route('login'));
    }
}
