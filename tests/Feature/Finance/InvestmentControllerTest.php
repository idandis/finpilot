<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);
        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        // A buy trade: cash goes out.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 200,
        ]);
        // A sell trade: cash comes back.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-10',
            'direction' => 'income',
            'amount' => 50,
        ]);
        // An unrelated grocery expense must not count towards the investment flow.
        Transaction::factory()->for($account, 'financialAccount')->create([
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
        $cardOne = Card::factory()->for($account, 'financialAccount')->create(['name' => 'Trade Republic']);
        $cardTwo = Card::factory()->for($account, 'financialAccount')->create(['name' => 'Altra carta']);
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

    public function test_it_reports_an_open_position_after_a_buy()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
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
        );
    }

    public function test_it_reports_a_closed_position_with_realized_gain_after_a_full_sell()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IT0005495657 SAIPEM, quantity: 91.0',
            'isin' => 'IT0005495657',
            'quantity' => 91.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
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
}
