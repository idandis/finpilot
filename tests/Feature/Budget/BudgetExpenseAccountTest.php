<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetSubcategory;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Da dove passano i soldi: ogni movimento può indicare un conto, e senza
 * conto vale come pagato in contanti.
 */
class BudgetExpenseAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_movement_remembers_the_account_it_came_from()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $subcategory = $this->subcategory($user, 'Bollette', 'Enel');

        $this->actingAs($user)
            ->post(route('budget-expenses.store'), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $account->id,
                'amount' => 80,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertSame($account->id, MonthlyBudget::query()->sole()->expenses()->sole()->financial_account_id);
    }

    public function test_without_an_account_the_movement_is_in_cash()
    {
        $user = User::factory()->create();
        $subcategory = $this->subcategory($user, 'Bollette', 'Enel');

        $this->actingAs($user)
            ->post(route('budget-expenses.store'), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 15,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertNull(MonthlyBudget::query()->sole()->expenses()->sole()->financial_account_id);
    }

    public function test_the_account_of_someone_else_does_not_end_up_on_the_movement()
    {
        $user = User::factory()->create();
        $stranger = FinancialAccount::factory()->create();
        $subcategory = $this->subcategory($user, 'Bollette', 'Enel');

        $this->actingAs($user)
            ->post(route('budget-expenses.store'), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $stranger->id,
                'amount' => 80,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertNull(MonthlyBudget::query()->sole()->expenses()->sole()->financial_account_id);
    }

    public function test_the_account_can_be_changed_afterwards()
    {
        $user = User::factory()->create();
        $card = FinancialAccount::factory()->for($user)->create();
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->subcategory($user, 'Bollette', 'Enel');

        $expense = $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $card->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($user)
            ->put(route('budget-expenses.update', $expense), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => null,
                'amount' => 80,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertNull($expense->fresh()->financial_account_id);
    }

    public function test_the_monthly_budget_shows_the_accounts_and_the_one_of_each_movement()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['name' => 'Intesa quotidiano']);
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->subcategory($user, 'Bollette', 'Enel');

        $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $account->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Intesa quotidiano')
                ->where('transactions.0.account_id', $account->id)
                ->where('transactions.0.account_name', 'Intesa quotidiano')
            );
    }

    /**
     * Chi condivide il budget registra i movimenti nel budget dell'altra
     * persona, ma con le proprie carte: i conti restano di chi li possiede.
     */
    public function test_a_member_records_in_the_shared_budget_with_his_own_account()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $his = FinancialAccount::factory()->for($member)->create();
        $subcategory = $this->subcategory($owner, 'Bollette', 'Enel');

        $this->actingAs($member)
            ->post(route('budget-expenses.store'), [
                'budget_user_id' => $owner->id,
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $his->id,
                'amount' => 80,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertSame($his->id, $owner->monthlyBudgets()->sole()->expenses()->sole()->financial_account_id);
    }

    /** Il conto del proprietario, per il membro, vale come non indicato. */
    public function test_a_member_cannot_pin_a_movement_on_the_card_of_the_owner()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $hers = FinancialAccount::factory()->for($owner)->create();
        $subcategory = $this->subcategory($owner, 'Bollette', 'Enel');

        $this->actingAs($member)
            ->post(route('budget-expenses.store'), [
                'budget_user_id' => $owner->id,
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $hers->id,
                'amount' => 80,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertNull($owner->monthlyBudgets()->sole()->expenses()->sole()->financial_account_id);
    }

    /**
     * Correggere l'importo di una spesa altrui non le stacca il conto di
     * sotto solo perché quel conto non è nostro.
     */
    public function test_editing_someone_elses_movement_keeps_its_card()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $hers = FinancialAccount::factory()->for($owner)->create();
        $subcategory = $this->subcategory($owner, 'Bollette', 'Enel');
        $september = MonthlyBudget::create(['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);

        $expense = $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $hers->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($member)
            ->put(route('budget-expenses.update', $expense), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'financial_account_id' => $hers->id,
                'amount' => 95,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertSame($hers->id, $expense->fresh()->financial_account_id);
    }

    private function subcategory(User $user, string $category, string $name): BudgetSubcategory
    {
        return $user->budgetCategories()->create([
            'name' => $category,
            'color' => '#3b82f6',
            'type' => BudgetCategory::TYPE_EXPENSE,
        ])->subcategories()->create(['name' => $name]);
    }
}
