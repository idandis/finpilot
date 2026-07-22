<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_categorize_their_own_transaction()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($account, 'financialAccount')->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->patch(route('transactions.update', $transaction), [
            'transaction_category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'transaction_category_id' => $category->id,
        ]);
    }

    public function test_a_user_can_edit_their_own_transactions_description()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($account, 'financialAccount')->create([
            'description' => 'Descrizione originale',
        ]);

        $response = $this->actingAs($user)->patch(route('transactions.update', $transaction), [
            'description' => 'Esselunga - spesa settimanale',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'Esselunga - spesa settimanale',
        ]);
    }

    public function test_a_user_cannot_edit_another_users_transaction_description()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $transaction = Transaction::factory()->for($otherAccount, 'financialAccount')->create([
            'description' => 'Descrizione originale',
        ]);

        $response = $this->actingAs($user)->patch(route('transactions.update', $transaction), [
            'description' => 'Modificata',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'description' => 'Descrizione originale',
        ]);
    }

    public function test_a_user_cannot_categorize_another_users_transaction()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $transaction = Transaction::factory()->for($otherAccount, 'financialAccount')->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->patch(route('transactions.update', $transaction), [
            'transaction_category_id' => $category->id,
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_transaction()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($account, 'financialAccount')->create();

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $transaction));

        $response->assertRedirect();
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_a_user_cannot_delete_another_users_transaction()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $transaction = Transaction::factory()->for($otherAccount, 'financialAccount')->create();

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $transaction));

        $response->assertForbidden();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_a_user_can_delete_all_transactions_for_their_own_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        Transaction::factory()->for($account, 'financialAccount')->count(3)->create();

        $response = $this->actingAs($user)->delete(route('accounts.transactions.destroy', $account));

        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_a_user_cannot_delete_all_transactions_for_another_users_account()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        Transaction::factory()->for($otherAccount, 'financialAccount')->count(3)->create();

        $response = $this->actingAs($user)->delete(route('accounts.transactions.destroy', $otherAccount));

        $response->assertForbidden();
        $this->assertDatabaseCount('transactions', 3);
    }
}
