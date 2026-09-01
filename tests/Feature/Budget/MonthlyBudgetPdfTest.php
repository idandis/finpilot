<?php

namespace Tests\Feature\Budget;

use App\Models\BudgetCategory;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyBudgetPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_the_planned_budget_as_a_pdf()
    {
        $user = User::factory()->create();

        $august = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 8]);

        $category = $user->budgetCategories()->create([
            'name' => 'Bollette',
            'color' => '#3b82f6',
            'type' => BudgetCategory::TYPE_EXPENSE,
            'monthly_budget_id' => null,
        ]);
        $subcategory = $category->subcategories()->create(['name' => 'Gas', 'monthly_budget_id' => null]);

        $august->budgetLines()->create([
            'budget_subcategory_id' => $subcategory->id,
            'planned_amount' => 120,
        ]);

        $response = $this->actingAs($user)
            ->get(route('monthly-budgets.pdf', ['year' => 2026, 'month' => 8]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_pdf_requires_authentication()
    {
        $this->get(route('monthly-budgets.pdf'))->assertRedirect(route('login'));
    }
}
