<?php

namespace Tests\Feature\Budget;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il budget che si apre all'avvio.
 *
 * Chi tiene i conti sul budget condiviso da un'altra persona può eleggerlo a
 * predefinito e ritrovarcisi dentro senza sceglierlo dal menù.
 */
class DefaultBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_choice_the_budget_is_your_own()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('monthly-budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->where('budget.id', $user->id)
                ->where('budget.is_owner', true)
                ->where('budgets.0.is_default', true)
            );
    }

    public function test_a_shared_budget_can_become_the_one_that_opens_first()
    {
        [$owner, $member] = $this->sharedBudget();

        $this->actingAs($member)
            ->post(route('budget.default'), ['budget_user_id' => $owner->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($owner->id, $member->fresh()->default_budget_user_id);

        // Aprendo l'app senza chiedere niente si arriva sul budget dell'altra.
        $this->actingAs($member)
            ->get(route('monthly-budgets.index'))
            ->assertInertia(fn ($page) => $page
                ->where('budget.id', $owner->id)
                ->where('budget.is_owner', false)
                ->where('budgets.1.is_default', true)
                ->where('budgets.0.is_default', false)
            );
    }

    /** Tornare al proprio si salva come "nessuna scelta". */
    public function test_choosing_your_own_budget_clears_the_preference()
    {
        [$owner, $member] = $this->sharedBudget();
        $member->update(['default_budget_user_id' => $owner->id]);

        $this->actingAs($member)
            ->post(route('budget.default'), ['budget_user_id' => $member->id])
            ->assertRedirect();

        $this->assertNull($member->fresh()->default_budget_user_id);
    }

    /** Un budget indicato nell'URL vince sul predefinito. */
    public function test_an_explicit_budget_wins_over_the_default()
    {
        [$owner, $member] = $this->sharedBudget();
        $member->update(['default_budget_user_id' => $owner->id]);

        $this->actingAs($member)
            ->get(route('monthly-budgets.index', ['budget' => $member->id]))
            ->assertInertia(fn ($page) => $page->where('budget.id', $member->id));
    }

    /** Tolta la condivisione si torna al proprio, senza dire niente. */
    public function test_losing_access_falls_back_to_your_own_budget()
    {
        [$owner, $member] = $this->sharedBudget();
        $member->update(['default_budget_user_id' => $owner->id]);

        $owner->budgetMembers()->detach($member->id);

        $this->actingAs($member)
            ->get(route('monthly-budgets.index'))
            ->assertInertia(fn ($page) => $page->where('budget.id', $member->id));
    }

    public function test_the_budget_of_a_stranger_cannot_become_the_default()
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budget.default'), ['budget_user_id' => $stranger->id])
            ->assertForbidden();

        $this->assertNull($user->fresh()->default_budget_user_id);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function sharedBudget(): array
    {
        $owner = User::factory()->create(['name' => 'Yana']);
        $member = User::factory()->create();
        $owner->budgetMembers()->attach($member->id);

        return [$owner, $member];
    }
}
