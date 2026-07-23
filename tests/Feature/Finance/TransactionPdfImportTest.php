<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TransactionPdfImportTest extends TestCase
{
    use RefreshDatabase;

    private function statementFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'estratto.pdf',
            file_get_contents(base_path('tests/Fixtures/trade-republic-statement.pdf')),
        );
    }

    /**
     * The fixture mirrors Trade Republic's own PDF template: a summary
     * table with the starting balance, then a transaction table where each
     * row only carries a running balance - not an explicit income/expense
     * label - so direction has to be inferred from how the balance moved.
     */
    public function test_a_user_can_import_a_trade_republic_pdf_statement()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->statementFile(),
        ]);

        $response->assertRedirect(route('cards.show', ['card' => $card, 'year' => 2025, 'month' => 5]));
        $this->assertDatabaseCount('transactions', 3);

        $transfer = Transaction::where('description', 'Incoming transfer from MARIO ROSSI')->first();
        $this->assertNotNull($transfer);
        $this->assertSame('income', $transfer->direction);
        $this->assertEquals(500.00, $transfer->amount);
        $this->assertSame('2025-05-02', $transfer->transaction_date->format('Y-m-d'));

        $trade = Transaction::where('description', 'like', 'Buy trade IE00BK5BQT80%')->first();
        $this->assertNotNull($trade);
        $this->assertSame('expense', $trade->direction);
        $this->assertEquals(200.00, $trade->amount);
        $this->assertSame('Investimenti', $trade->category?->name);
        $this->assertSame('IE00BK5BQT80', $trade->isin);
        $this->assertEquals(1.5, $trade->quantity);

        $cardPurchase = Transaction::where('description', 'RYANAIR ABCDEF')->first();
        $this->assertNotNull($cardPurchase);
        $this->assertSame('expense', $cardPurchase->direction);
        $this->assertEquals(50.50, $cardPurchase->amount);
    }

    public function test_reimporting_the_same_pdf_statement_does_not_create_duplicates()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->statementFile(),
        ]);
        $this->assertDatabaseCount('transactions', 3);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->statementFile(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_it_rejects_a_pdf_that_is_not_a_trade_republic_statement()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->createWithContent(
            'documento.pdf',
            file_get_contents(base_path('tests/Fixtures/not-a-statement.pdf')),
        );

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_a_user_cannot_import_a_pdf_into_another_users_card()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $otherCard = Card::factory()->for($otherAccount, 'financialAccount')->create();

        $response = $this->actingAs($user)->post(route('transactions.import', $otherCard), [
            'file' => $this->statementFile(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('transactions', 0);
    }
}
