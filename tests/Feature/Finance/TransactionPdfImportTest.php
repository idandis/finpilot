<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Finance\TransactionPdfImporter;
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
     * Synthetic fixture (built with tests/Fixtures/generate-trade-republic-statement.php)
     * that exercises every "tipo" and description variant actually observed
     * in a real Trade Republic statement, beyond the 3 scenarios covered by
     * the original fixture above.
     */
    private function extendedStatementFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'estratto-esteso.pdf',
            file_get_contents(base_path('tests/Fixtures/trade-republic-statement-extended.pdf')),
        );
    }

    private function unreconciledStatementFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'estratto-non-riconciliato.pdf',
            file_get_contents(base_path('tests/Fixtures/trade-republic-statement-unreconciled.pdf')),
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

    public function test_it_imports_interest_rows_regardless_of_wording()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        foreach (['Your interest payment', 'Interest payment'] as $description) {
            $interest = Transaction::where('description', $description)->first();
            $this->assertNotNull($interest, "Missing transaction: {$description}");
            $this->assertSame('income', $interest->direction);
            $this->assertSame('Investimenti', $interest->category?->name);
            $this->assertNull($interest->isin);
        }
    }

    public function test_it_imports_tax_rows_including_a_non_trade_cancellation()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        $stampDuty = Transaction::where('description', 'Stamp Duty Tax (Portfolio)')->first();
        $this->assertNotNull($stampDuty);
        $this->assertSame('expense', $stampDuty->direction);
        $this->assertSame('Investimenti', $stampDuty->category?->name);

        // A "Cancellation" prefix outside of a trade row must not trigger
        // the trade regex and must not extract a bogus ISIN/quantity.
        $cancellation = Transaction::where('description', 'Cancellation Stamp Duty Tax (Portfolio)')->first();
        $this->assertNotNull($cancellation);
        $this->assertSame('income', $cancellation->direction);
        $this->assertSame('Investimenti', $cancellation->category?->name);
        $this->assertNull($cancellation->isin);
        $this->assertNull($cancellation->quantity);
    }

    public function test_it_imports_reward_rows()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        foreach (['Your Saveback payment', 'Cash reward allocation'] as $description) {
            $reward = Transaction::where('description', $description)->first();
            $this->assertNotNull($reward, "Missing transaction: {$description}");
            $this->assertSame('income', $reward->direction);
            $this->assertSame('Investimenti', $reward->category?->name);
            $this->assertNull($reward->isin);
        }
    }

    public function test_it_extracts_the_isin_from_a_dividend_row()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        $dividend = Transaction::where('description', 'Cash Dividend for ISIN XF000ETH0019')->first();
        $this->assertNotNull($dividend);
        $this->assertSame('income', $dividend->direction);
        $this->assertSame('Investimenti', $dividend->category?->name);
        $this->assertSame('XF000ETH0019', $dividend->isin);
        $this->assertNull($dividend->quantity);
    }

    public function test_a_cancellation_buy_trade_extracts_isin_and_quantity_like_the_original_trade()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        $cancellation = Transaction::where('description', 'like', 'Cancellation Buy trade US84615Q1031%')->first();
        $this->assertNotNull($cancellation);
        $this->assertSame('income', $cancellation->direction);
        $this->assertSame('US84615Q1031', $cancellation->isin);
        $this->assertEquals(1.078399, $cancellation->quantity);
    }

    public function test_it_imports_incoming_and_outgoing_bonifico_with_third_party_iban_in_description()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $this->extendedStatementFile(),
        ]);

        $incoming = Transaction::where('description', 'Incoming transfer from CAMBIAGHI FRANCESCA (IT20J36772223000EM000911706)')->first();
        $this->assertNotNull($incoming);
        $this->assertSame('income', $incoming->direction);
        $this->assertEquals(500.00, $incoming->amount);

        $outgoing = Transaction::where('description', 'Outgoing transfer to MISSIAGLIA ALESSANDRO ALDO (IT60X0542811101000000123456)')->first();
        $this->assertNotNull($outgoing);
        $this->assertSame('expense', $outgoing->direction);
        $this->assertEquals(300.00, $outgoing->amount);
    }

    public function test_it_reports_the_first_unreconciled_row_in_the_import_result()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $result = app(TransactionPdfImporter::class)->import($card, $this->unreconciledStatementFile());

        $this->assertNull($result['error']);
        $this->assertSame(1, $result['skipped']);
        $this->assertCount(1, $result['skipped_rows']);
        $this->assertSame('2025-05-05', $result['skipped_rows'][0]['date']);
        $this->assertSame('Bonifico', $result['skipped_rows'][0]['tipo']);
        $this->assertStringContainsString('MISSIAGLIA ALESSANDRO ALDO', $result['skipped_rows'][0]['description']);
    }
}
