<?php

namespace Tests\Feature\Budget;

use App\Models\AccountTransfer;
use App\Models\BudgetCategory;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I giri di soldi tra i propri conti: spostano i saldi senza contare come
 * entrate o uscite del mese.
 */
class AccountTransferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_money_from_one_account_to_another()
    {
        $user = User::factory()->create();
        $from = FinancialAccount::factory()->for($user)->create(['name' => 'Conto', 'initial_balance' => 1000]);
        $to = FinancialAccount::factory()->for($user)->create(['name' => 'Revolut', 'initial_balance' => 0]);

        $this->actingAs($user)
            ->post(route('account-transfers.store'), $this->payload([
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'amount' => 250,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                // Ordinati per nome: prima "Conto", poi "Revolut".
                ->where('accounts.0.balance', fn ($balance) => (float) $balance === 750.0)
                ->where('accounts.1.balance', fn ($balance) => (float) $balance === 250.0)
            );
    }

    /** Il prelievo: esce dal conto e diventa contante, che non è un conto. */
    public function test_it_accepts_a_withdrawal_towards_cash()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 500]);

        $this->actingAs($user)
            ->post(route('account-transfers.store'), $this->payload([
                'from_financial_account_id' => $account->id,
                'to_financial_account_id' => null,
                'amount' => 100,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.balance', fn ($balance) => (float) $balance === 400.0)
            );
    }

    public function test_it_refuses_a_transfer_between_the_same_two_sides()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('account-transfers.store'), $this->payload([
                'from_financial_account_id' => $account->id,
                'to_financial_account_id' => $account->id,
            ]))
            ->assertSessionHasErrors('to_financial_account_id');

        // Da contanti a contanti non sposta niente.
        $this->actingAs($user)
            ->post(route('account-transfers.store'), $this->payload())
            ->assertSessionHasErrors('to_financial_account_id');

        $this->assertSame(0, AccountTransfer::query()->count());
    }

    public function test_the_account_of_someone_else_counts_as_cash()
    {
        $user = User::factory()->create();
        $mine = FinancialAccount::factory()->for($user)->create();
        $stranger = FinancialAccount::factory()->create();

        $this->actingAs($user)
            ->post(route('account-transfers.store'), $this->payload([
                'from_financial_account_id' => $mine->id,
                'to_financial_account_id' => $stranger->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(AccountTransfer::query()->sole()->to_financial_account_id);
    }

    public function test_a_transfer_shows_up_among_the_movements_of_the_month()
    {
        $user = User::factory()->create();
        $from = FinancialAccount::factory()->for($user)->create(['name' => 'Conto']);
        $to = FinancialAccount::factory()->for($user)->create(['name' => 'Revolut']);

        $user->accountTransfers()->create([
            'from_financial_account_id' => $from->id,
            'to_financial_account_id' => $to->id,
            'amount' => 250,
            'description' => 'Ricarica',
            'transferred_at' => '2026-09-10 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.kind', 'transfer')
                ->where('transactions.0.description', 'Ricarica')
                ->where('transactions.0.account_name', 'Conto')
                ->where('transactions.0.to_account_name', 'Revolut')
                ->where('transactions.0.category_id', null)
            );
    }

    public function test_a_transfer_does_not_count_as_income_or_expense_of_the_month()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);

        $subcategory = $user->budgetCategories()->create([
            'name' => 'Bollette',
            'color' => '#3b82f6',
            'type' => BudgetCategory::TYPE_EXPENSE,
        ])->subcategories()->create(['name' => 'Enel']);

        $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $account->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $user->accountTransfers()->create([
            'from_financial_account_id' => $account->id,
            'to_financial_account_id' => null,
            'amount' => 500,
            'transferred_at' => '2026-09-05 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('transactions', 2)
                // Le somme per voce, da cui si costruiscono i totali del mese,
                // vedono solo gli 80 € della bolletta.
                ->where("expenses.{$subcategory->id}", fn ($total) => (float) $total === 80.0)
            );
    }

    public function test_transfers_of_another_month_stay_out()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();

        $user->accountTransfers()->create([
            'from_financial_account_id' => $account->id,
            'amount' => 100,
            'transferred_at' => '2026-08-31 23:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page->has('transactions', 0));
    }

    public function test_the_transfers_of_someone_else_stay_out_of_reach()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $transfer = $other->accountTransfers()->create([
            'amount' => 100,
            'from_financial_account_id' => FinancialAccount::factory()->for($other)->create()->id,
            'transferred_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($user)
            ->put(route('account-transfers.update', $transfer), $this->payload())
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('account-transfers.destroy', $transfer))
            ->assertForbidden();
    }

    /** I conti non si condividono col budget: il membro muove solo i suoi. */
    public function test_a_member_moves_money_only_between_his_own_accounts()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $his = FinancialAccount::factory()->for($member)->create();
        $hers = FinancialAccount::factory()->for($member)->create();

        $this->actingAs($member)
            ->post(route('account-transfers.store'), $this->payload([
                'budget_user_id' => $owner->id,
                'from_financial_account_id' => $his->id,
                'to_financial_account_id' => $hers->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($member->id, AccountTransfer::query()->sole()->user_id);
    }

    /** Due conti del proprietario, per il membro, valgono come nessun conto. */
    public function test_a_member_cannot_move_money_on_the_accounts_of_the_owner()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $from = FinancialAccount::factory()->for($owner)->create();
        $to = FinancialAccount::factory()->for($owner)->create();

        $this->actingAs($member)
            ->post(route('account-transfers.store'), $this->payload([
                'budget_user_id' => $owner->id,
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
            ]))
            ->assertSessionHasErrors('to_financial_account_id');

        $this->assertSame(0, AccountTransfer::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'from_financial_account_id' => null,
            'to_financial_account_id' => null,
            'amount' => 100,
            'recorded_at' => '2026-09-04 10:00',
        ], $overrides);
    }
}
