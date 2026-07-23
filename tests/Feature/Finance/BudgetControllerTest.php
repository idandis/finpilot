<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\CategoryBudget;
use App\Models\FinancialAccount;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('budgets.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_shows_every_visible_category_with_its_budget_when_set()
    {
        $user = User::factory()->create();
        $groceries = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Trasporti']);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $groceries->id,
            'monthly_amount' => 300,
        ]);

        $response = $this->actingAs($user)->get(route('budgets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('budgets', 2)
            ->where('budgets.0.name', 'Alimentari')
            ->where('budgets.0.monthly_budget', 300)
            ->where('budgets.1.name', 'Trasporti')
            ->where('budgets.1.monthly_budget', null)
            ->where('totalBudget', 300)
        );
    }

    public function test_a_user_can_set_a_budget_for_a_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Casa']);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'monthly_amount' => 200,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 200,
        ]);
    }

    public function test_a_user_can_update_an_existing_budget()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 100,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'monthly_amount' => 250,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 250,
        ]);
        $this->assertDatabaseCount('category_budgets', 1);
    }

    public function test_a_user_can_clear_a_budget_by_sending_null()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 100,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'monthly_amount' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
        ]);
    }

    public function test_a_user_cannot_set_a_budget_for_another_users_category()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => User::factory()]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'monthly_amount' => 100,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('category_budgets', [
            'transaction_category_id' => $category->id,
        ]);
    }

    public function test_a_user_can_assign_a_card_to_a_budget()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->create(['user_id' => $user->id]);
        $card = Card::factory()->create(['financial_account_id' => $account->id]);
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 200,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'card_id' => $card->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => $card->id,
            'monthly_amount' => 200,
        ]);
    }

    public function test_changing_the_card_does_not_alter_the_budget_amount()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->create(['user_id' => $user->id]);
        $cardOne = Card::factory()->create(['financial_account_id' => $account->id]);
        $cardTwo = Card::factory()->create(['financial_account_id' => $account->id]);
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => $cardOne->id,
            'monthly_amount' => 200,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'card_id' => $cardTwo->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => $cardTwo->id,
            'monthly_amount' => 200,
        ]);
    }

    public function test_changing_the_amount_does_not_alter_the_assigned_card()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->create(['user_id' => $user->id]);
        $card = Card::factory()->create(['financial_account_id' => $account->id]);
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => $card->id,
            'monthly_amount' => 200,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'monthly_amount' => 250,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => $card->id,
            'monthly_amount' => 250,
        ]);
    }

    public function test_a_user_cannot_assign_another_users_card_to_a_budget()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->create();
        $otherCard = Card::factory()->create(['financial_account_id' => $otherAccount->id]);
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'monthly_amount' => 200,
        ]);

        $response = $this->actingAs($user)->patch(route('budgets.update', $category), [
            'card_id' => $otherCard->id,
        ]);

        $response->assertInvalid(['card_id']);
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'card_id' => null,
        ]);
    }

    public function test_budgets_are_scoped_per_user_for_shared_system_categories()
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        $this->actingAs($userOne)->patch(route('budgets.update', $category), ['monthly_amount' => 300]);
        $this->actingAs($userTwo)->patch(route('budgets.update', $category), ['monthly_amount' => 150]);

        $this->assertDatabaseHas('category_budgets', ['user_id' => $userOne->id, 'monthly_amount' => 300]);
        $this->assertDatabaseHas('category_budgets', ['user_id' => $userTwo->id, 'monthly_amount' => 150]);

        $response = $this->actingAs($userOne)->get(route('budgets.index'));
        $response->assertInertia(fn ($page) => $page->where('budgets.0.monthly_budget', 300));
    }
}
