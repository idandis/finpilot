<?php

namespace Tests\Feature\Budget;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ordine dei conti, deciso trascinando le righe.
 *
 * Quello che si sceglie in "Conti e carte" vale dappertutto: le tile del mese
 * e il selettore dei movimenti leggono lo stesso elenco.
 */
class BudgetAccountOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_order_chosen_by_dragging_is_kept()
    {
        $user = User::factory()->create();
        $accounts = $this->accounts($user, ['Alfa', 'Beta', 'Gamma']);

        $this->actingAs($user)
            ->post(route('budget-accounts.reorder'), [
                'ids' => [$accounts['Gamma'], $accounts['Alfa'], $accounts['Beta']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.name', 'Gamma')
                ->where('accounts.1.name', 'Alfa')
                ->where('accounts.2.name', 'Beta')
            );
    }

    /** Le tile del mese e il selettore dei movimenti leggono lo stesso elenco. */
    public function test_the_monthly_budget_follows_the_same_order()
    {
        $user = User::factory()->create();
        $accounts = $this->accounts($user, ['Alfa', 'Beta', 'Gamma']);

        $this->actingAs($user)
            ->post(route('budget-accounts.reorder'), [
                'ids' => [$accounts['Gamma'], $accounts['Beta'], $accounts['Alfa']],
            ]);

        $this->actingAs($user)
            ->get(route('monthly-budgets.index', ['year' => 2026, 'month' => 9]))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.0.name', 'Gamma')
                ->where('accounts.1.name', 'Beta')
                ->where('accounts.2.name', 'Alfa')
            );

        $this->actingAs($user)
            ->get(route('budget-expenses.index'))
            ->assertInertia(fn ($page) => $page->where('accounts.0.name', 'Gamma'));
    }

    public function test_a_new_account_lands_at_the_end()
    {
        $user = User::factory()->create();
        $this->accounts($user, ['Alfa', 'Beta']);

        $this->actingAs($user)
            ->post(route('budget-accounts.store'), [
                'name' => 'Aaa ultimo arrivato',
                'type' => 'checking',
                'initial_balance' => 0,
                'color' => '#3B82F6',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Alfabeticamente sarebbe il primo: conta la posizione, non il nome.
        $this->actingAs($user)
            ->get(route('budget-accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('accounts.2.name', 'Aaa ultimo arrivato')
            );
    }

    public function test_the_accounts_of_someone_else_cannot_be_reordered()
    {
        $user = User::factory()->create();
        $stranger = FinancialAccount::factory()->create();

        $this->actingAs($user)
            ->post(route('budget-accounts.reorder'), ['ids' => [$stranger->id]])
            ->assertForbidden();
    }

    /** I conti non si condividono: il membro non riordina quelli del proprietario. */
    public function test_a_member_of_a_shared_budget_cannot_reorder_the_accounts_of_the_owner()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        $accounts = $this->accounts($owner, ['Alfa', 'Beta']);

        $this->actingAs($member)
            ->post(route('budget-accounts.reorder'), [
                'ids' => [$accounts['Beta'], $accounts['Alfa']],
            ])
            ->assertForbidden();

        $this->assertSame(1, FinancialAccount::query()->find($accounts['Beta'])->position);
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, int>
     */
    private function accounts(User $user, array $names): array
    {
        $ids = [];

        foreach ($names as $position => $name) {
            $ids[$name] = FinancialAccount::factory()->for($user)->create([
                'name' => $name,
                'position' => $position,
            ])->id;
        }

        return $ids;
    }
}
