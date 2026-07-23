<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\CategoryRule;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Finance\TransactionCategorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TransactionCategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_categorizing_an_uncategorized_transaction_creates_a_rule()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($account, 'financialAccount')->create([
            'description' => 'Esselunga Milano',
            'transaction_category_id' => null,
        ]);
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        $this->actingAs($user)->patch(route('transactions.update', $transaction), [
            'transaction_category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('category_rules', [
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'pattern' => 'esselunga',
            'times_applied' => 1,
        ]);
    }

    public function test_correcting_a_transaction_again_reinforces_the_existing_rule_instead_of_duplicating_it()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);
        $otherCategory = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Shopping']);

        $first = Transaction::factory()->for($account, 'financialAccount')->create(['description' => 'Esselunga Milano']);
        $second = Transaction::factory()->for($account, 'financialAccount')->create(['description' => 'Esselunga Bergamo']);

        $this->actingAs($user)->patch(route('transactions.update', $first), [
            'transaction_category_id' => $category->id,
        ]);
        $this->actingAs($user)->patch(route('transactions.update', $second), [
            'transaction_category_id' => $otherCategory->id,
        ]);

        $this->assertDatabaseCount('category_rules', 1);
        $this->assertDatabaseHas('category_rules', [
            'user_id' => $user->id,
            'pattern' => 'esselunga',
            'transaction_category_id' => $otherCategory->id,
            'times_applied' => 2,
        ]);
    }

    public function test_import_auto_categorizes_a_transaction_matching_an_existing_rule()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Abbonamenti']);

        CategoryRule::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'pattern' => 'netflix',
        ]);

        $csv = implode("\n", [
            'Data;Descrizione;Importo',
            '01/07/2026;NETFLIX.COM;-15,99',
        ]);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('estratto.csv', $csv),
        ]);

        $transaction = Transaction::where('description', 'NETFLIX.COM')->first();
        $this->assertNotNull($transaction);
        $this->assertSame($category->id, $transaction->transaction_category_id);
    }

    public function test_categorizer_ignores_inactive_rules()
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => null]);
        CategoryRule::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'pattern' => 'netflix',
            'is_active' => false,
        ]);

        $result = (new TransactionCategorizer())->categorize('NETFLIX.COM', $user->id);

        $this->assertNull($result);
    }
}
