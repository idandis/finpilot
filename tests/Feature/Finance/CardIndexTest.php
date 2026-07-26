<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('cards.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_only_sees_cards_from_their_own_accounts()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'name' => 'La mia carta',
        ]);

        $otherUser = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for($otherUser)->create();
        Card::factory()->for($otherAccount, 'financialAccount')->create([
            'user_id' => $otherUser->id,
            'name' => 'Carta altrui',
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('cards', 1)
            ->where('cards.0.name', 'La mia carta')
        );
    }

    public function test_the_page_loads_with_no_cards()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('cards', 0)->has('overviewTabs', 0));
    }

    public function test_it_groups_a_cards_income_and_expense_by_year_and_month()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta A']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 1500,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-07-10',
            'direction' => 'expense',
            'amount' => 400,
        ]);
        // A different card's transactions must not be included.
        $otherCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta B']);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $otherCard->id,
            'transaction_date' => '2026-07-15',
            'direction' => 'expense',
            'amount' => 100,
        ]);
        // A different year.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2025-03-01',
            'direction' => 'income',
            'amount' => 1200,
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overviewTabs', 2)
            ->where('overviewTabs.0.id', (string) $card->id)
            ->has('overviewTabs.0.overview', 2)
            // Most recent year first.
            ->where('overviewTabs.0.overview.0.year', 2026)
            ->where('overviewTabs.0.overview.0.totals.income', 1500)
            ->where('overviewTabs.0.overview.0.totals.expense', 400)
            ->has('overviewTabs.0.overview.0.months', 12)
            // July is month index 6 (0-based) in the 1..12 range.
            ->where('overviewTabs.0.overview.0.months.6.month', 7)
            ->where('overviewTabs.0.overview.0.months.6.income', 1500)
            ->where('overviewTabs.0.overview.0.months.6.expense', 400)
            ->where('overviewTabs.0.overview.1.year', 2025)
            ->where('overviewTabs.0.overview.1.totals.income', 1200)
            ->where('overviewTabs.0.overview.1.totals.expense', 0)
        );
    }

    public function test_the_overview_does_not_include_another_users_transactions()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        Transaction::factory()->for($otherAccount, 'financialAccount')->create([
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overviewTabs', 1)
            ->where('overviewTabs.0.overview.0.totals.income', 0)
            ->where('overviewTabs.0.overview.0.totals.expense', 0)
        );
    }

    public function test_the_overview_adds_one_tab_per_card_scoped_to_that_cards_own_transactions()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $cardOne = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta Uno']);
        $cardTwo = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'name' => 'Carta Due']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $cardOne->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 100,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $cardTwo->id,
            'transaction_date' => '2026-07-06',
            'direction' => 'expense',
            'amount' => 40,
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overviewTabs', 2)
            ->where('overviewTabs.0.name', 'Carta Due')
            ->where('overviewTabs.0.overview.0.totals.income', 0)
            ->where('overviewTabs.0.overview.0.totals.expense', 40)
            ->where('overviewTabs.1.name', 'Carta Uno')
            ->where('overviewTabs.1.overview.0.totals.income', 100)
            ->where('overviewTabs.1.overview.0.totals.expense', 0)
        );
    }

    public function test_the_overview_breaks_down_yearly_expenses_by_category()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari', 'color' => '#123456']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $groceries->id,
            'transaction_date' => '2026-02-01',
            'direction' => 'expense',
            'amount' => 300,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => null,
            'transaction_date' => '2026-05-01',
            'direction' => 'expense',
            'amount' => 50,
        ]);
        // Income must not count as a category expense.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $groceries->id,
            'transaction_date' => '2026-06-01',
            'direction' => 'income',
            'amount' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overviewTabs.0.overview.0.categoryBreakdown', 2)
            ->where('overviewTabs.0.overview.0.categoryBreakdown.0.name', 'Alimentari')
            ->where('overviewTabs.0.overview.0.categoryBreakdown.0.color', '#123456')
            ->where('overviewTabs.0.overview.0.categoryBreakdown.0.amount', 300)
            ->where('overviewTabs.0.overview.0.categoryBreakdown.1.name', 'Non categorizzato')
            ->where('overviewTabs.0.overview.0.categoryBreakdown.1.category_id', null)
            ->where('overviewTabs.0.overview.0.categoryBreakdown.1.amount', 50)
        );
    }

    public function test_the_overview_excludes_investment_transactions_from_totals_and_category_breakdown()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti', 'color' => '#654321']);
        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari', 'color' => '#123456']);

        // A stock buy: cash out, but not "spending" - must not affect the overview.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 500,
        ]);
        // A stock sell: cash in, also excluded.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-07-06',
            'direction' => 'income',
            'amount' => 520,
        ]);
        // A real grocery expense must still count.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $groceries->id,
            'transaction_date' => '2026-07-07',
            'direction' => 'expense',
            'amount' => 60,
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('overviewTabs.0.overview.0.totals.income', 0)
            ->where('overviewTabs.0.overview.0.totals.expense', 60)
            ->has('overviewTabs.0.overview.0.categoryBreakdown', 1)
            ->where('overviewTabs.0.overview.0.categoryBreakdown.0.name', 'Alimentari')
            ->where('overviewTabs.0.overview.0.categoryBreakdown.0.amount', 60)
        );
    }
}
