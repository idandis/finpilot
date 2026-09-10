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
 * Chi ha registrato il movimento.
 *
 * In un budget condiviso i movimenti stanno sotto al proprietario ma li
 * scrive chi capita: la firma resta attaccata a chi l'ha messo giù.
 */
class BudgetMovementAuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_movement_remembers_who_wrote_it()
    {
        [$owner, $member] = $this->sharedBudget();
        $subcategory = $this->subcategory($owner, 'Spesa', 'Supermercato');

        $this->actingAs($member)
            ->post(route('budget-expenses.store'), [
                'budget_user_id' => $owner->id,
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 40,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $expense = $owner->monthlyBudgets()->sole()->expenses()->sole();

        $this->assertSame($member->id, $expense->recorded_by_user_id);
    }

    /** Correggere un movimento non lo fa diventare proprio. */
    public function test_editing_a_movement_leaves_the_signature_alone()
    {
        [$owner, $member] = $this->sharedBudget();
        $subcategory = $this->subcategory($owner, 'Spesa', 'Supermercato');
        $september = MonthlyBudget::create(['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);

        $expense = $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'recorded_by_user_id' => $member->id,
            'amount' => 40,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($owner)
            ->put(route('budget-expenses.update', $expense), [
                'year' => 2026,
                'month' => 9,
                'budget_subcategory_id' => $subcategory->id,
                'amount' => 55,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect();

        $this->assertSame($member->id, $expense->fresh()->recorded_by_user_id);
    }

    public function test_the_month_says_who_wrote_each_movement()
    {
        [$owner, $member] = $this->sharedBudget();
        $subcategory = $this->subcategory($owner, 'Spesa', 'Supermercato');
        $september = MonthlyBudget::create(['user_id' => $owner->id, 'year' => 2026, 'month' => 9]);

        $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'recorded_by_user_id' => $member->id,
            'amount' => 40,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($owner)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9, 'budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->where('transactions.0.recorded_by_id', $member->id)
                ->where('transactions.0.recorded_by_name', $member->name)
            );
    }

    /** Sui movimenti scritti prima che si tenesse il conto non c'è firma da mostrare. */
    public function test_an_old_movement_has_no_signature()
    {
        $user = User::factory()->create();
        $subcategory = $this->subcategory($user, 'Spesa', 'Supermercato');
        $september = MonthlyBudget::create(['user_id' => $user->id, 'year' => 2026, 'month' => 9]);

        $september->expenses()->create([
            'budget_subcategory_id' => $subcategory->id,
            'amount' => 40,
            'recorded_at' => '2026-09-04 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->where('transactions.0.recorded_by_id', null)
                ->where('transactions.0.recorded_by_name', null)
            );
    }

    public function test_a_transfer_remembers_who_wrote_it()
    {
        [$owner, $member] = $this->sharedBudget();
        // I conti sono personali: il membro muove i suoi.
        $from = FinancialAccount::factory()->for($member)->create();
        $to = FinancialAccount::factory()->for($member)->create();

        $this->actingAs($member)
            ->post(route('account-transfers.store'), [
                'budget_user_id' => $owner->id,
                'from_financial_account_id' => $from->id,
                'to_financial_account_id' => $to->id,
                'amount' => 100,
                'recorded_at' => '2026-09-04 10:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = $member->accountTransfers()->sole();

        $this->assertSame($member->id, $transfer->recorded_by_user_id);

        // Un trasferimento muove i propri conti: lo rivede chi l'ha fatto,
        // anche mentre guarda il budget condiviso dell'altra persona.
        $this->actingAs($member)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9, 'budget' => $owner->id]))
            ->assertInertia(fn ($page) => $page
                ->where('transactions.0.recorded_by_name', $member->name)
            );
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function sharedBudget(): array
    {
        $owner = User::factory()->create(['name' => 'Yana']);
        $member = User::factory()->create(['name' => 'Iana Longo']);
        $owner->budgetMembers()->attach($member->id);

        return [$owner, $member];
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
