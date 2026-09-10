<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetSubcategory;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('budget-categories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_shows_only_the_categories_shared_by_every_month()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);

        $this->category($user, 'Bollette');
        $this->category($user, 'Vacanza', $august);

        $response = $this->actingAs($user)->get(route('budget-categories.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Bollette')
        );
    }

    public function test_it_leaves_out_the_subcategories_of_a_single_month()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');

        $this->subcategory($category, 'Enel');
        $this->subcategory($category, 'Condizionatore', $august);

        $this->actingAs($user)
            ->get(route('budget-categories.index'))
            ->assertInertia(fn ($page) => $page
                ->has('categories.0.subcategories', 1)
                ->where('categories.0.subcategories.0.name', 'Enel')
            );
    }

    public function test_it_creates_a_category_limited_to_the_given_month()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Vacanza',
            'color' => '#10b981',
            'type' => 'expense',
            'scope' => 'month',
            'year' => 2026,
            'month' => 8,
        ]);

        $response->assertSessionHasNoErrors();

        $monthlyBudget = MonthlyBudget::where('user_id', $user->id)->where('year', 2026)->where('month', 8)->firstOrFail();
        $this->assertDatabaseHas('budget_categories', [
            'user_id' => $user->id,
            'name' => 'Vacanza',
            'monthly_budget_id' => $monthlyBudget->id,
        ]);
    }

    public function test_it_creates_a_category_shared_by_every_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Bollette',
            'color' => '#3b82f6',
            'type' => 'expense',
            'scope' => 'global',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_categories', [
            'user_id' => $user->id,
            'name' => 'Bollette',
            'monthly_budget_id' => null,
        ]);
    }

    public function test_the_same_name_can_be_reused_in_a_different_scope_but_not_in_the_same_one()
    {
        $user = User::factory()->create();
        $this->category($user, 'Vacanza');

        $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Vacanza',
            'color' => '#10b981',
            'type' => 'expense',
            'scope' => 'month',
            'year' => 2026,
            'month' => 8,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Vacanza',
            'color' => '#10b981',
            'type' => 'expense',
            'scope' => 'global',
        ])->assertSessionHasErrors('name');

        $this->assertSame(2, BudgetCategory::where('name', 'Vacanza')->count());
    }

    public function test_it_creates_an_income_category()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Stipendio',
            'color' => '#10b981',
            'type' => 'income',
            'scope' => 'global',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_categories', [
            'user_id' => $user->id,
            'name' => 'Stipendio',
            'type' => 'income',
            'monthly_budget_id' => null,
        ]);
    }

    public function test_it_refuses_an_unknown_category_type()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('budget-categories.store'), [
            'name' => 'Boh',
            'color' => '#10b981',
            'type' => 'savings',
            'scope' => 'global',
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('budget_categories', 0);
    }

    public function test_it_adds_a_month_only_subcategory_to_a_shared_category()
    {
        $user = User::factory()->create();
        $category = $this->category($user, 'Bollette');

        $this->actingAs($user)->post(route('budget-categories.subcategories.store', $category), [
            'name' => 'Condizionatore',
            'scope' => 'month',
            'year' => 2026,
            'month' => 8,
        ])->assertSessionHasNoErrors();

        $monthlyBudget = MonthlyBudget::where('user_id', $user->id)->where('year', 2026)->where('month', 8)->firstOrFail();
        $this->assertDatabaseHas('budget_subcategories', [
            'budget_category_id' => $category->id,
            'name' => 'Condizionatore',
            'monthly_budget_id' => $monthlyBudget->id,
        ]);
    }

    public function test_a_subcategory_of_a_temporary_category_stays_in_that_month()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Vacanza', $august);

        // Anche chiedendo lo scope comune, la sottocategoria segue il mese
        // della categoria che la contiene.
        $this->actingAs($user)->post(route('budget-categories.subcategories.store', $category), [
            'name' => 'Hotel',
            'scope' => 'global',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('budget_subcategories', [
            'budget_category_id' => $category->id,
            'name' => 'Hotel',
            'monthly_budget_id' => $august->id,
        ]);
    }

    public function test_deleting_the_monthly_budget_removes_its_temporary_categories_only()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $shared = $this->category($user, 'Bollette');
        $temporary = $this->category($user, 'Vacanza', $august);
        $temporarySubcategory = $this->subcategory($shared, 'Condizionatore', $august);

        $august->delete();

        $this->assertDatabaseHas('budget_categories', ['id' => $shared->id]);
        $this->assertDatabaseMissing('budget_categories', ['id' => $temporary->id]);
        $this->assertDatabaseMissing('budget_subcategories', ['id' => $temporarySubcategory->id]);
    }

    public function test_it_deletes_a_category_with_its_lines_and_movements()
    {
        $user = User::factory()->create();
        $august = $this->monthlyBudget($user, 2026, 8);
        $category = $this->category($user, 'Bollette');
        $subcategory = $this->subcategory($category, 'Enel');

        $august->budgetLines()->create([
            'budget_subcategory_id' => $subcategory->id,
            'planned_amount' => 200,
        ]);
        $august->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 60,
            'recorded_at' => '2026-08-14 18:30:00',
        ]);

        $this->actingAs($user)
            ->delete(route('budget-categories.destroy', $category))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('budget_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('budget_subcategories', ['id' => $subcategory->id]);
        $this->assertDatabaseCount('monthly_budget_lines', 0);
        $this->assertDatabaseCount('budget_expenses', 0);
    }

    public function test_it_saves_the_order_of_the_categories()
    {
        $user = User::factory()->create();
        $first = $this->category($user, 'Bollette');
        $second = $this->category($user, 'Casa');

        $this->actingAs($user)
            ->post(route('budget-categories.reorder'), ['ids' => [$second->id, $first->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $second->fresh()->order);
        $this->assertSame(1, $first->fresh()->order);

        $this->actingAs($user)
            ->get(route('budget-categories.index'))
            ->assertInertia(fn ($page) => $page->where('categories.0.name', 'Casa'));
    }

    public function test_it_refuses_to_reorder_categories_of_another_user()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = $this->category($owner, 'Bollette');

        $this->actingAs($intruder)
            ->post(route('budget-categories.reorder'), ['ids' => [$category->id]])
            ->assertForbidden();

        $this->assertSame(0, $category->fresh()->order);
    }

    public function test_it_refuses_to_touch_categories_of_another_user()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = $this->category($owner, 'Bollette');
        $subcategory = $this->subcategory($category, 'Enel');

        $this->actingAs($intruder)->delete(route('budget-categories.destroy', $category))->assertForbidden();
        $this->actingAs($intruder)->delete(route('budget-subcategories.destroy', $subcategory))->assertForbidden();

        $this->assertDatabaseHas('budget_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('budget_subcategories', ['id' => $subcategory->id]);
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
