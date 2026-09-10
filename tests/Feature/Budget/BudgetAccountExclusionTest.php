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
 * Un conto escluso dalle statistiche.
 *
 * Non è archiviato - resta usabile e il suo saldo si calcola ancora - ma per
 * il budget del mese i movimenti che ci passano non sono mai successi: né
 * nell'elenco, né nello speso delle voci.
 */
class BudgetAccountExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_movements_of_an_excluded_account_stay_out_of_the_month()
    {
        $user = User::factory()->create();
        $excluded = FinancialAccount::factory()->for($user)->create([
            'name' => 'Carta di lavoro',
            'excluded_from_stats' => true,
        ]);
        $everyday = FinancialAccount::factory()->for($user)->create(['name' => 'Revolut']);
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->subcategory($user, 'Spesa', 'Supermercato');

        $this->movement($september, $subcategory, $excluded->id, 200);
        $this->movement($september, $subcategory, $everyday->id, 30);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('transactions', 1)
                ->where('transactions.0.account_id', $everyday->id)
                ->where('expenses.'.$subcategory->id, fn ($total) => (float) $total === 30.0)
            );
    }

    /** Senza conto un movimento non è di nessuno: nessuna esclusione lo tocca. */
    public function test_a_movement_without_an_account_survives_the_exclusion()
    {
        $user = User::factory()->create();
        FinancialAccount::factory()->for($user)->create(['excluded_from_stats' => true]);
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);
        $subcategory = $this->subcategory($user, 'Spesa', 'Supermercato');

        $this->movement($september, $subcategory, null, 45);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('transactions', 1)
                ->where('expenses.'.$subcategory->id, fn ($total) => (float) $total === 45.0)
            );
    }

    /** Un giro di soldi basta che tocchi il conto escluso da una delle due parti. */
    public function test_a_transfer_that_touches_an_excluded_account_disappears()
    {
        $user = User::factory()->create();
        $excluded = FinancialAccount::factory()->for($user)->create(['excluded_from_stats' => true]);
        $from = FinancialAccount::factory()->for($user)->create();
        $to = FinancialAccount::factory()->for($user)->create();

        $this->transfer($user, $from->id, $excluded->id);
        $this->transfer($user, $excluded->id, $to->id);
        $this->transfer($user, $from->id, $to->id);
        $this->transfer($user, $from->id, null);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page->has('transactions', 2));
    }

    /** Il conto resta in elenco e scegliibile: escluso non vuol dire archiviato. */
    public function test_an_excluded_account_is_still_offered_to_the_movements()
    {
        $user = User::factory()->create();
        FinancialAccount::factory()->for($user)->create([
            'name' => 'Carta di lavoro',
            'excluded_from_stats' => true,
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.excluded_from_stats', true)
                ->where('accounts.0.hidden_from_stats', false)
            );
    }

    public function test_the_flag_is_saved_from_the_account_form()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['excluded_from_stats' => false]);

        $this->actingAs($user)
            ->put(route('budget-accounts.update', $account), [
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => 0,
                'color' => '#3B82F6',
                'excluded_from_stats' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($account->fresh()->excluded_from_stats);
    }

    private function movement(MonthlyBudget $budget, BudgetSubcategory $subcategory, ?int $accountId, float $amount): void
    {
        $budget->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'financial_account_id' => $accountId,
            'amount' => $amount,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);
    }

    private function transfer(User $user, ?int $fromId, ?int $toId): void
    {
        $user->accountTransfers()->create([
            'from_financial_account_id' => $fromId,
            'to_financial_account_id' => $toId,
            'amount' => 50,
            'transferred_at' => '2026-09-04 10:00:00',
        ]);
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
