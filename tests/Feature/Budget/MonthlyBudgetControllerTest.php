<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetSubcategory;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_planned_amounts_and_spending_side_by_side()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $subcategory = $this->subcategory($category, 'Enel');

        $august->budgetLines()->create([
            'budget_subcategory_id' => $subcategory->id,
            'planned_amount' => 200,
        ]);
        $august->expenses()->createMany([
            [
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 80,
                'recorded_at' => '2026-08-04 10:00:00',
            ],
            [
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 40,
                'recorded_at' => '2026-08-11 10:00:00',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 8]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where("budgetLines.{$subcategory->id}", fn ($amount) => (float) $amount === 200.0)
            ->where("expenses.{$subcategory->id}", fn ($amount) => (float) $amount === 120.0)
        );
    }

    public function test_it_lists_the_expenses_of_the_month_newest_first()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $subcategory = $this->subcategory($category, 'Enel');

        $august->expenses()->createMany([
            [
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 80,
                'description' => 'Bolletta luglio',
                'recorded_at' => '2026-08-04 10:00:00',
            ],
            [
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 40,
                'description' => 'Conguaglio',
                'recorded_at' => '2026-08-11 10:00:00',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 8]));

        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 2)
            ->where('transactions.0.description', 'Conguaglio')
            ->where('transactions.0.category_name', 'Bollette')
            ->where('transactions.0.subcategory_name', 'Enel')
            ->where('transactions.1.description', 'Bolletta luglio')
        );
    }

    public function test_it_marks_the_direction_of_categories_and_movements()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $salary = $this->category($user, 'Lavoro', null, BudgetCategory::TYPE_INCOME);
        $payslip = $this->subcategory($salary, 'Stipendio');
        $bills = $this->category($user, 'Bollette');
        $enel = $this->subcategory($bills, 'Enel');

        $august->expenses()->createMany([
            [
                'budget_subcategory_id' => $payslip->id,
                'amount' => 1800,
                'recorded_at' => '2026-08-27 09:00:00',
            ],
            [
                'budget_subcategory_id' => $enel->id,
                'amount' => 60,
                'recorded_at' => '2026-08-28 09:00:00',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 8]));

        $response->assertInertia(fn ($page) => $page
            ->where('categories.0.type', 'income')
            ->where('categories.1.type', 'expense')
            ->where('transactions.0.direction', 'expense')
            ->where('transactions.1.direction', 'income')
        );
    }

    public function test_it_saves_the_planned_amount_of_a_month_only_subcategory()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $temporary = $this->subcategory($category, 'Condizionatore', $august);

        $this->actingAs($user)->post(route('monthly-budgets.store'), [
            'year' => 2026,
            'month' => 8,
            'budget_lines' => [
                ['subcategory_id' => $temporary->id, 'planned_amount' => 150],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budget_lines', [
            'monthly_budget_id' => $august->id,
            'budget_subcategory_id' => $temporary->id,
            'planned_amount' => 150,
        ]);
    }

    public function test_it_ignores_planned_amounts_for_subcategories_outside_the_month()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $september = $this->monthlyBudget($user, 2026, 9);
        $category = $this->category($user, 'Bollette');
        $otherMonth = $this->subcategory($category, 'Regali', $september);
        $stranger = $this->subcategory($this->category(User::factory()->create(), 'Altro'), 'Enel');

        $this->actingAs($user)->post(route('monthly-budgets.store'), [
            'year' => 2026,
            'month' => 8,
            'budget_lines' => [
                ['subcategory_id' => $otherMonth->id, 'planned_amount' => 150],
                ['subcategory_id' => $stranger->id, 'planned_amount' => 90],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('monthly_budget_lines', 0);
        $this->assertSame($august->id, MonthlyBudget::where('year', 2026)->where('month', 8)->value('id'));
    }

    public function test_an_expense_lowers_what_is_left_on_a_month_only_subcategory()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $temporary = $this->subcategory($category, 'Condizionatore', $august);

        $august->budgetLines()->create([
            'budget_subcategory_id' => $temporary->id,
            'planned_amount' => 150,
        ]);

        $this->actingAs($user)->post(route('budget-expenses.store'), [
            'year' => 2026,
            'month' => 8,
            'budget_subcategory_id' => $temporary->id,
            'amount' => 60,
            'description' => 'Acconto',
            'recorded_at' => '2026-08-14 18:30',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 8]))
            ->assertInertia(fn ($page) => $page
                ->where("budgetLines.{$temporary->id}", fn ($amount) => (float) $amount === 150.0)
                ->where("expenses.{$temporary->id}", fn ($amount) => (float) $amount === 60.0)
            );
    }

    public function test_an_expense_is_refused_on_a_subcategory_of_another_month()
    {
        $user = User::factory()->create();
        $september = $this->monthlyBudget($user, 2026, 9);
        $category = $this->category($user, 'Bollette');
        $otherMonth = $this->subcategory($category, 'Regali', $september);

        $this->actingAs($user)->post(route('budget-expenses.store'), [
            'year' => 2026,
            'month' => 8,
            'budget_subcategory_id' => $otherMonth->id,
            'amount' => 60,
            'recorded_at' => '2026-08-14 18:30',
        ])->assertSessionHasErrors('budget_subcategory_id');

        $this->assertDatabaseCount('budget_expenses', 0);
    }

    public function test_it_updates_a_movement()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $gas = $this->subcategory($category, 'Gas');
        $luce = $this->subcategory($category, 'Luce');

        $expense = $august->expenses()->create([
            'budget_subcategory_id' => $gas->id,
            'amount' => 50,
            'description' => 'Bolletta',
            'recorded_at' => '2026-08-14 18:30',
        ]);

        $this->actingAs($user)->put(route('budget-expenses.update', $expense), [
            'year' => 2026,
            'month' => 8,
            'budget_subcategory_id' => $luce->id,
            'amount' => 65.5,
            'description' => 'Conguaglio',
            'recorded_at' => '2026-08-15 09:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_expenses', [
            'id' => $expense->id,
            'budget_subcategory_id' => $luce->id,
            'amount' => 65.5,
            'description' => 'Conguaglio',
        ]);
    }

    public function test_it_refuses_to_update_a_movement_of_another_user()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $august = $this->monthlyBudget($owner, 2026, 8);
        $subcategory = $this->subcategory($this->category($owner, 'Bollette'), 'Gas');

        $expense = $august->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 50,
            'recorded_at' => '2026-08-14 18:30',
        ]);

        $this->actingAs($intruder)->put(route('budget-expenses.update', $expense), [
            'year' => 2026,
            'month' => 8,
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 999,
            'recorded_at' => '2026-08-15 09:00',
        ])->assertForbidden();

        $this->assertDatabaseHas('budget_expenses', ['id' => $expense->id, 'amount' => 50]);
    }

    private function monthlyBudget(User $user, int $year, int $month): MonthlyBudget
    {
        return MonthlyBudget::create([
            'user_id' => $user->id,
            'year' => $year,
            'month' => $month,
        ]);
    }

    private function category(
        User $user,
        string $name,
        ?MonthlyBudget $monthlyBudget = null,
        string $type = BudgetCategory::TYPE_EXPENSE,
    ): BudgetCategory {
        return $user->budgetCategories()->create([
            'name' => $name,
            'color' => '#3b82f6',
            'type' => $type,
            'monthly_budget_id' => $monthlyBudget?->id,
        ]);
    }

    private function subcategory(BudgetCategory $category, string $name, ?MonthlyBudget $monthlyBudget = null): BudgetSubcategory
    {
        return $category->subcategories()->create([
            'name' => $name,
            'monthly_budget_id' => $monthlyBudget?->id,
        ]);
    }
}
