<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\InstrumentPrice;
use App\Models\InstrumentPriceHistory;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketControllerTest extends TestCase
{
    use RefreshDatabase;

    private function investmentCard(User $user): Card
    {
        $account = FinancialAccount::factory()->for($user)->create();

        return Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'is_investment_card' => true,
        ]);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('market.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_lists_open_positions_with_candles_and_day_change()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
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
        InstrumentPriceHistory::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'price_date' => '2026-07-20',
            'close_price' => 100.0,
            'open_price' => 99.0,
            'high_price' => 101.0,
            'low_price' => 98.5,
        ]);
        InstrumentPriceHistory::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'price_date' => '2026-07-21',
            'close_price' => 110.0,
            'open_price' => 100.0,
            'high_price' => 112.0,
            'low_price' => 99.0,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('instruments', 1)
            ->where('instruments.0.isin', 'IE00BK5BQT80')
            ->where('instruments.0.is_crypto', false)
            ->has('instruments.0.candles', 2)
            ->where('instruments.0.candles.0.open', fn ($value) => (float) $value === 99.0)
            ->where('instruments.0.candles.1.close', fn ($value) => (float) $value === 110.0)
            ->where('instruments.0.day_change_percent', fn ($value) => (float) $value === 10.0)
            ->where('instruments.0.analysis.sma20', null)
            ->where('instruments.0.analysis.rsi14', null)
            ->where('instruments.0.analysis.support', null)
            ->where('instruments.0.analysis.resistance', null)
        );
    }

    public function test_it_computes_moving_average_rsi_and_support_resistance_with_enough_history()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
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

        // 25 strictly increasing closes (100..124): sma20 averages the last
        // 20 (105..124 => 114.5), rsi14 is 100 since every step is a gain,
        // and being monotonic there is no interior swing high/low, so
        // support/resistance stay null.
        foreach (range(0, 24) as $offset) {
            $close = 100.0 + $offset;
            InstrumentPriceHistory::factory()->create([
                'isin' => 'IE00BK5BQT80',
                'price_date' => now()->subDays(24 - $offset)->format('Y-m-d'),
                'close_price' => $close,
                'open_price' => $close,
                'high_price' => $close + 0.5,
                'low_price' => $close - 0.5,
            ]);
        }

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('instruments.0.analysis.sma20', fn ($value) => (float) $value === 114.5)
            ->where('instruments.0.analysis.sma20_signal', 'above')
            ->where('instruments.0.analysis.rsi14', fn ($value) => (float) $value === 100.0)
            ->where('instruments.0.analysis.rsi14_signal', 'overbought')
            ->where('instruments.0.analysis.support', null)
            ->where('instruments.0.analysis.resistance', null)
        );
    }

    public function test_it_reports_null_day_change_when_history_has_fewer_than_two_points()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('instruments', 1)
            ->has('instruments.0.candles', 0)
            ->where('instruments.0.day_change', null)
            ->where('instruments.0.day_change_percent', null)
        );
    }

    public function test_it_excludes_closed_positions()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-05',
            'description' => 'Buy trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-10',
            'description' => 'Sell trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'income',
            'amount' => 210,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('instruments', 0));
    }

    public function test_it_does_not_include_a_non_investment_cards_positions()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $regularCard = Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'is_investment_card' => false,
        ]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $regularCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('instruments', 0));
    }

    public function test_it_does_not_include_another_users_positions()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCard = $this->investmentCard($otherUser);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($otherCard->financialAccount, 'financialAccount')->create([
            'card_id' => $otherCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('instruments', 0));
    }

    public function test_it_flags_crypto_positions()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade XF000BTC0017 Bitcoin, quantity: 0.01',
            'isin' => 'XF000BTC0017',
            'quantity' => 0.01,
            'direction' => 'expense',
            'amount' => 300,
        ]);

        $response = $this->actingAs($user)->get(route('market.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('instruments', 1)
            ->where('instruments.0.is_crypto', true)
        );
    }
}
