<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\FinancialAccount;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_the_shared_budget_instead_of_their_own()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $category = $this->category($owner, 'Bollette');

        $this->actingAs($member)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9, 'budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.name', $category->name)
                ->where('budget.is_owner', false)
                ->where('budget.id', $owner->id)
            );
    }

    public function test_without_the_budget_parameter_everyone_sees_their_own()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);
        $this->category($owner, 'Bollette');

        $this->actingAs($member)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->where('categories', [])
                ->where('budget.is_owner', true)
            );
    }

    public function test_a_stranger_cannot_open_someone_elses_budget()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->category($owner, 'Bollette');

        // Nessun 403: si ricade sul proprio budget, che è vuoto.
        $this->actingAs($stranger)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9, 'budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->where('categories', [])
                ->where('budget.is_owner', true)
            );
    }

    public function test_a_member_records_a_movement_on_the_shared_budget()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $subcategory = $this->category($owner, 'Bollette')
            ->subcategories()->create(['name' => 'Gas', 'monthly_budget_id' => null]);

        $this->actingAs($member)->post(route('budget-expenses.store'), [
            'budget_user_id' => $owner->id,
            'year' => 2026,
            'month' => 9,
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 40,
            'recorded_at' => '2026-09-01 10:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', ['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);
        $this->assertSame(1, MonthlyBudget::where('user_id', $owner->id)->sole()->expenses()->count());
        $this->assertSame(0, MonthlyBudget::where('user_id', $member->id)->count());
    }

    public function test_a_stranger_cannot_touch_a_movement_of_another_budget()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $september = MonthlyBudget::create(['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->category($owner, 'Bollette')
            ->subcategories()->create(['name' => 'Gas', 'monthly_budget_id' => null]);
        $expense = $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 40,
            'recorded_at' => '2026-09-01 10:00',
        ]);

        $this->actingAs($stranger)
            ->delete(route('budget-expenses.destroy', $expense))
            ->assertForbidden();
    }

    public function test_the_owner_invites_by_email_and_removes_a_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('budget.members.store'), ['email' => $member->email])
            ->assertSessionHasNoErrors();

        $this->assertTrue($owner->fresh()->budgetMembers()->whereKey($member->id)->exists());

        $this->actingAs($owner)
            ->delete(route('budget.members.destroy', [$owner->id, $member->id]));

        $this->assertFalse($owner->fresh()->budgetMembers()->whereKey($member->id)->exists());
    }

    public function test_a_member_can_leave_but_cannot_remove_others()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $owner->budgetMembers()->attach([$member->id, $other->id]);

        $this->actingAs($member)
            ->delete(route('budget.members.destroy', [$owner->id, $other->id]))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('budget.members.destroy', [$owner->id, $member->id]));

        $this->assertFalse($owner->fresh()->budgetMembers()->whereKey($member->id)->exists());
    }

    /**
     * Il budget si condivide, i conti no.
     *
     * Chi riceve il budget vede i movimenti dell'altra persona, carta di
     * provenienza compresa - è scritta sul movimento - ma dei conti veri e
     * propri, con i loro saldi, vede solo i suoi.
     */
    public function test_a_member_sees_the_movements_of_the_owner_but_only_his_own_accounts()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $hers = FinancialAccount::factory()->for($owner)->create([
            'name' => 'Carta di lei',
            'initial_balance' => 5000,
        ]);
        FinancialAccount::factory()->for($member)->create(['name' => 'Carta di lui']);

        $september = MonthlyBudget::create(['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->category($owner, 'Bollette')->subcategories()->create(['name' => 'Enel']);

        $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $hers->id,
            'amount' => 80,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($member)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9, 'budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                // Il movimento c'è, e dice con che carta è stato pagato.
                ->has('transactions', 1)
                ->where('transactions.0.account_name', 'Carta di lei')
                // Dei conti, però, si vede solo il proprio.
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Carta di lui')
            );
    }

    private function category(User $user, string $name): BudgetCategory
    {
        return $user->budgetCategories()->create([
            'name' => $name,
            'color' => '#3b82f6',
            'type' => BudgetCategory::TYPE_EXPENSE,
            'monthly_budget_id' => null,
        ]);
    }
}
