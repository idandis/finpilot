<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_category_breakdown_of_expenses_for_the_selected_month()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari', 'color' => '#008300']);
        $transport = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Trasporti', 'color' => '#c98500']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-07-05',
            'direction' => 'expense',
            'amount' => 60,
            'transaction_category_id' => $groceries->id,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-07-10',
            'direction' => 'expense',
            'amount' => 40,
            'transaction_category_id' => $groceries->id,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-07-12',
            'direction' => 'expense',
            'amount' => 30,
            'transaction_category_id' => $transport->id,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-07-15',
            'direction' => 'expense',
            'amount' => 15,
            'transaction_category_id' => null,
        ]);
        // Income must not appear in the expense breakdown.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-07-01',
            'direction' => 'income',
            'amount' => 1500,
            'transaction_category_id' => $groceries->id,
        ]);
        // Outside the selected month: must not be included.
        Transaction::factory()->for($account, 'financialAccount')->create([
            'transaction_date' => '2026-06-01',
            'direction' => 'expense',
            'amount' => 999,
            'transaction_category_id' => $groceries->id,
        ]);

        $response = $this->actingAs($user)->get(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 7]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categoryBreakdown', 3)
            ->where('categoryBreakdown.0.name', 'Alimentari')
            ->where('categoryBreakdown.0.amount', 100)
            ->where('categoryBreakdown.0.color', '#008300')
            ->where('categoryBreakdown.1.name', 'Trasporti')
            ->where('categoryBreakdown.1.amount', 30)
            ->where('categoryBreakdown.2.name', 'Non categorizzato')
            ->where('categoryBreakdown.2.category_id', null)
            ->where('categoryBreakdown.2.amount', 15)
        );
    }

    public function test_the_breakdown_is_empty_when_there_are_no_expenses()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $response = $this->actingAs($user)->get(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 7]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('categoryBreakdown', 0));
    }
}
