<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_it_shows_all_of_the_users_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Trade Republic']);
        Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta di debito']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('cards', 2));
    }

    public function test_it_only_includes_investment_data_for_investment_flagged_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $regularCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
        // Belongs to a non-investment card - must not count towards the
        // dashboard's combined positions/portfolio history.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $regularCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('positions.open', 1)
            ->where('positions.open.0.isin', 'IE00BK5BQT80')
            ->where('positions.open.0.invested', 200)
        );
    }

    public function test_it_reports_the_account_balance_for_the_users_investment_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $investmentCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('accountBalance', 800));
    }

    public function test_it_reports_a_null_account_balance_when_there_are_no_investment_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('accountBalance', null));
    }
}
