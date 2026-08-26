<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetMonthClosure;
use App\Models\MonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('balance-sheet.ledger'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_lists_the_closed_months_newest_first()
    {
        $user = User::factory()->create();

        BalanceSheetMonthClosure::factory()->for($user)->create(['year' => 2026, 'month' => 7]);
        BalanceSheetMonthClosure::factory()->for($user)->create([
            'year' => 2026,
            'month' => 8,
            'cash_flow' => -120,
        ]);

        $response = $this->actingAs($user)->get(route('balance-sheet.ledger'));

        $response->assertInertia(fn ($page) => $page
            ->has('closures', 2)
            ->where('closures.0.month', 8)
            ->where('closures.0.cash_flow', -120)
            ->where('closures.1.month', 7)
        );
    }

    public function test_it_reports_the_monthly_budget_totals()
    {
        $user = User::factory()->create();
        $august = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 8]);

        $salary = $user->budgetCategories()->create([
            'name' => 'Lavoro',
            'color' => '#10b981',
            'type' => 'income',
        ]);
        $bills = $user->budgetCategories()->create([
            'name' => 'Bollette',
            'color' => '#3b82f6',
            'type' => 'expense',
        ]);

        $august->expenses()->createMany([
            [
                'budget_subcategory_id' => $salary->subcategories()->create(['name' => 'Stipendio'])->id,
                'amount' => 1800,
                'recorded_at' => '2026-08-27 09:00:00',
            ],
            [
                'budget_subcategory_id' => $bills->subcategories()->create(['name' => 'Enel'])->id,
                'amount' => 60,
                'recorded_at' => '2026-08-28 09:00:00',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('balance-sheet.ledger'));

        $response->assertInertia(fn ($page) => $page
            ->has('budgetMonths', 1)
            ->where('budgetMonths.0.year', 2026)
            ->where('budgetMonths.0.month', 8)
            ->where('budgetMonths.0.income', fn ($amount) => (float) $amount === 1800.0)
            ->where('budgetMonths.0.expense', fn ($amount) => (float) $amount === 60.0)
        );
    }

    public function test_it_shows_every_entry_type_and_the_overview()
    {
        $user = User::factory()->create();
        BalanceSheetEntry::factory()->for($user)->type('income')->create(['name' => 'Stipendio', 'amount' => 2000]);
        BalanceSheetEntry::factory()->for($user)->type('asset')->create(['name' => 'Garage', 'amount' => 20000]);

        $response = $this->actingAs($user)->get(route('balance-sheet.ledger'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('balance-sheet/Ledger/Index')
            ->has('entries', 2)
            ->has('overview')
            ->has('profile')
            ->has('categorySuggestions')
            ->where('overview.monthly_income', 2000)
            ->where('overview.total_assets', 20000)
        );
    }

    public function test_it_only_shows_the_authenticated_users_entries()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        BalanceSheetEntry::factory()->for($user)->type('income')->create();
        BalanceSheetEntry::factory()->for($otherUser)->type('income')->create();

        $response = $this->actingAs($user)->get(route('balance-sheet.ledger'));

        $response->assertInertia(fn ($page) => $page->has('entries', 1));
    }
}
