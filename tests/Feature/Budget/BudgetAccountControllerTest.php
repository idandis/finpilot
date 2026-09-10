<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetSubcategory;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_with_its_card_details()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), [
                'name' => 'Intesa quotidiano',
                'type' => 'credit_card',
                'bank_name' => 'Intesa Sanpaolo',
                'holder_name' => 'Iana Longo',
                'iban' => 'IT60X0542811101000000123456',
                'initial_balance' => '1250.50',
                'color' => '#3B82F6',
                'icon' => 'credit-card',
            ])
            ->assertRedirect();

        $account = $user->financialAccounts()->sole();

        $this->assertSame('Intesa quotidiano', $account->name);
        $this->assertSame('credit_card', $account->type);
        $this->assertSame('IT60X0542811101000000123456', $account->iban);
        $this->assertSame('Iana Longo', $account->holder_name);
        $this->assertSame('1250.50', $account->initial_balance);
        $this->assertSame('EUR', $account->currency);
    }

    public function test_it_refuses_a_second_account_with_the_same_name()
    {
        $user = User::factory()->create();
        FinancialAccount::factory()->for($user)->create(['name' => 'Contanti di casa']);

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), $this->payload(['name' => 'Contanti di casa']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $user->financialAccounts()->count());
    }

    /** Anche il portafoglio è un conto: così i contanti hanno un saldo che si scala. */
    public function test_it_creates_a_cash_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), $this->payload([
                'name' => 'Portafoglio',
                'type' => 'cash',
                'initial_balance' => '60',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('cash', $user->financialAccounts()->sole()->type);
    }

    public function test_it_refuses_a_type_that_does_not_exist()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), $this->payload(['type' => 'bitcoin']))
            ->assertSessionHasErrors('type');
    }

    /** Il prelievo: dalla carta al portafoglio, e i due saldi si muovono. */
    public function test_a_withdrawal_moves_money_from_the_card_to_the_cash_account()
    {
        $user = User::factory()->create();
        $card = FinancialAccount::factory()->for($user)->create([
            'name' => 'Carta',
            'type' => 'credit_card',
            'initial_balance' => 500,
        ]);
        $wallet = FinancialAccount::factory()->for($user)->create([
            'name' => 'Portafoglio',
            'type' => 'cash',
            'initial_balance' => 20,
        ]);

        $this->actingAs($user)
            ->post(route('account-transfers.store'), [
                'from_financial_account_id' => $card->id,
                'to_financial_account_id' => $wallet->id,
                'amount' => 100,
                'recorded_at' => '2026-09-06 10:00',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.name', 'Carta')
                ->where('accounts.0.balance', fn ($balance) => (float) $balance === 400.0)
                ->where('accounts.1.name', 'Portafoglio')
                ->where('accounts.1.balance', fn ($balance) => (float) $balance === 120.0)
            );
    }

    /** Una spesa in contanti scala il portafoglio come farebbe una carta. */
    public function test_a_cash_expense_lowers_the_cash_account()
    {
        $user = User::factory()->create();
        $wallet = FinancialAccount::factory()->for($user)->create([
            'type' => 'cash',
            'initial_balance' => 120,
        ]);
        $subcategory = $this->subcategory($user, 'Alimentazione', 'Spesa');

        $this->actingAs($user)
            ->post(route('budget-expenses.store'), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $wallet->id,
                'amount' => 30,
                'recorded_at' => '2026-09-06 12:00',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.balance', fn ($balance) => (float) $balance === 90.0)
            );
    }

    public function test_the_balance_follows_the_movements_of_the_budget()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);

        $salary = $this->subcategory($user, 'Lavoro', 'Stipendio', BudgetCategory::TYPE_INCOME);
        $bills = $this->subcategory($user, 'Bollette', 'Enel');

        $september->expenses()->createMany([
            [
                'budget_subcategory_id' => $salary->id,
                'financial_account_id' => $account->id,
                'amount' => 1800,
                'recorded_at' => '2026-09-01 09:00:00',
            ],
            [
                'budget_subcategory_id' => $bills->id,
                'financial_account_id' => $account->id,
                'amount' => 300,
                'recorded_at' => '2026-09-02 09:00:00',
            ],
            // In contanti: non tocca nessun conto.
            [
                'budget_subcategory_id' => $bills->id,
                'financial_account_id' => null,
                'amount' => 50,
                'recorded_at' => '2026-09-03 09:00:00',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Budget/Accounts/Index')
                // 1000 di partenza, +1800 di stipendio, -300 di bolletta.
                ->where('accounts.0.balance', fn ($balance) => (float) $balance === 2500.0)
                ->where('accounts.0.movements_count', 2)
            );
    }

    public function test_an_account_can_be_kept_out_of_the_statistics()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), $this->payload(['hidden_from_stats' => true]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->financialAccounts()->sole()->hidden_from_stats);
    }

    public function test_the_hidden_flag_can_be_turned_on_and_off_from_the_edit_panel()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['hidden_from_stats' => false]);

        $this->actingAs($user)
            ->put(route('budget-accounts.update', $account), $this->payload([
                'name' => $account->name,
                'hidden_from_stats' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($account->fresh()->hidden_from_stats);

        $this->actingAs($user)
            ->put(route('budget-accounts.update', $account), $this->payload([
                'name' => $account->name,
                'hidden_from_stats' => false,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($account->fresh()->hidden_from_stats);
    }

    /** Archiviato significa fuori dai conteggi, non cancellato: il budget lo riceve marcato. */
    public function test_the_budget_knows_which_accounts_are_archived()
    {
        $user = User::factory()->create();
        FinancialAccount::factory()->for($user)->create([
            'name' => 'Aperto',
            'hidden_from_stats' => false,
        ]);
        FinancialAccount::factory()->for($user)->create([
            'name' => 'Archiviato',
            'hidden_from_stats' => true,
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('accounts', 2)
                ->where('accounts.0.name', 'Aperto')
                ->where('accounts.0.hidden_from_stats', false)
                ->where('accounts.1.name', 'Archiviato')
                ->where('accounts.1.hidden_from_stats', true)
            );
    }

    /**
     * Un budget condiviso mette in comune categorie e movimenti, non i conti:
     * chi lo riceve tiene i propri e non vede quelli di chi glielo ha dato.
     */
    public function test_a_member_of_a_shared_budget_keeps_his_own_accounts()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $hers = FinancialAccount::factory()->for($owner)->create(['name' => 'Carta di lei']);

        $this->actingAs($member)
            ->post(route('budget-accounts.store'), $this->payload(['budget_user_id' => $owner->id]))
            ->assertRedirect();

        $this->assertSame(1, $member->financialAccounts()->count());
        $this->assertSame(1, $owner->financialAccounts()->count());

        // In elenco ci sono solo i propri, anche chiedendo il budget dell'altra.
        $this->actingAs($member)
            ->get(route('budget-accounts.index', ['budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page->has('accounts', 1));

        $this->actingAs($member)
            ->put(route('budget-accounts.update', $hers), $this->payload())
            ->assertForbidden();
    }

    public function test_the_accounts_of_someone_else_stay_out_of_reach()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->create();

        $this->actingAs($user)
            ->put(route('budget-accounts.update', $account), $this->payload())
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('budget-accounts.destroy', $account))
            ->assertForbidden();
    }

    public function test_deleting_an_account_leaves_its_movements_in_the_budget()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);
        $bills = $this->subcategory($user, 'Bollette', 'Enel');

        $expense = $september->expenses()->create([
            'budget_subcategory_id' => $bills->id,
            'financial_account_id' => $account->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($user)
            ->delete(route('budget-accounts.destroy', $account))
            ->assertRedirect();

        $this->assertNull($expense->fresh()->financial_account_id);
        $this->assertSame(80.0, (float) $expense->fresh()->amount);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Carta principale',
            'type' => 'checking',
            'initial_balance' => '0',
            'color' => '#3B82F6',
        ], $overrides);
    }

    private function subcategory(User $user, string $category, string $name, string $type = BudgetCategory::TYPE_EXPENSE): BudgetSubcategory
    {
        return $user->budgetCategories()->create([
            'name' => $category,
            'color' => '#3b82f6',
            'type' => $type,
        ])->subcategories()->create(['name' => $name]);
    }
}
