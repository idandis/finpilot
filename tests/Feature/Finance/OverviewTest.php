<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('overview.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_groups_income_and_expense_by_year_and_month_across_all_accounts()
    {
        $user = User::factory()->create();
        $accountOne = FinancialAccount::factory()->for($user)->create();
        $accountTwo = FinancialAccount::factory()->for($user)->create();

        Transaction::factory()->for($accountOne, 'financialAccount')->create([
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 1500,
        ]);
        Transaction::factory()->for($accountOne, 'financialAccount')->create([
            'transaction_date' => '2026-07-10',
            'direction' => 'expense',
            'amount' => 400,
        ]);
        // A second account's transactions must be included too.
        Transaction::factory()->for($accountTwo, 'financialAccount')->create([
            'transaction_date' => '2026-07-15',
            'direction' => 'expense',
            'amount' => 100,
        ]);
        // A different year.
        Transaction::factory()->for($accountOne, 'financialAccount')->create([
            'transaction_date' => '2025-03-01',
            'direction' => 'income',
            'amount' => 1200,
        ]);

        $response = $this->actingAs($user)->get(route('overview.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overview', 2)
            // Most recent year first.
            ->where('overview.0.year', 2026)
            ->where('overview.0.totals.income', 1500)
            ->where('overview.0.totals.expense', 500)
            ->has('overview.0.months', 12)
            // July is month index 6 (0-based) in the 1..12 range.
            ->where('overview.0.months.6.month', 7)
            ->where('overview.0.months.6.income', 1500)
            ->where('overview.0.months.6.expense', 500)
            ->where('overview.1.year', 2025)
            ->where('overview.1.totals.income', 1200)
            ->where('overview.1.totals.expense', 0)
        );
    }

    public function test_it_does_not_include_another_users_transactions()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        Transaction::factory()->for($otherAccount, 'financialAccount')->create([
            'transaction_date' => '2026-07-05',
            'direction' => 'income',
            'amount' => 999,
        ]);

        $response = $this->actingAs($user)->get(route('overview.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('overview', 1)
            ->where('overview.0.totals.income', 0)
            ->where('overview.0.totals.expense', 0)
        );
    }
}
