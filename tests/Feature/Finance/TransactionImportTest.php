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

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private function statementCsv(): string
    {
        return implode("\n", [
            'Data;Descrizione;Importo;Categoria',
            '01/07/2026;ESSELUNGA MILANO;-45,50;Alimentari',
            '03/07/2026;STIPENDIO LUGLIO;1500,00;',
            '05/07/2026;NETFLIX.COM;-15,99;Abbonamenti',
            '15/08/2026;FUORI MESE;-10,00;',
        ]);
    }

    public function test_a_user_can_import_a_csv_statement_spanning_multiple_months()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Alimentari']);

        $file = UploadedFile::fake()->createWithContent('estratto.csv', $this->statementCsv());

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => $file,
        ]);

        // Every valid row is imported regardless of the month it falls in,
        // and the user lands on the month of the most recent transaction (August).
        $response->assertRedirect(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 8]));
        $this->assertDatabaseCount('transactions', 4);

        $groceries = Transaction::where('description', 'ESSELUNGA MILANO')->first();
        $this->assertNotNull($groceries);
        $this->assertSame('expense', $groceries->direction);
        $this->assertEquals(45.50, $groceries->amount);
        $this->assertSame('Alimentari', $groceries->category?->name);

        $salary = Transaction::where('description', 'STIPENDIO LUGLIO')->first();
        $this->assertSame('income', $salary->direction);
        $this->assertEquals(1500.00, $salary->amount);

        $outOfSelectedMonth = Transaction::where('description', 'FUORI MESE')->first();
        $this->assertNotNull($outOfSelectedMonth, 'Rows outside the currently viewed month must still be imported.');
    }

    public function test_reimporting_the_same_statement_does_not_create_duplicates()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $csv = $this->statementCsv();

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('estratto.csv', $csv),
        ]);
        $this->assertDatabaseCount('transactions', 4);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('estratto.csv', $csv),
        ]);

        // Still 4: the second import recognised all rows as duplicates.
        $this->assertDatabaseCount('transactions', 4);
    }

    public function test_a_user_cannot_import_into_another_users_card()
    {
        $user = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for(User::factory())->create();
        $otherCard = Card::factory()->for($otherAccount, 'financialAccount')->create();

        $response = $this->actingAs($user)->post(route('transactions.import', $otherCard), [
            'file' => UploadedFile::fake()->createWithContent('estratto.csv', $this->statementCsv()),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * Mirrors the real export format used by Revolut: comma-delimited, a
     * "Data di completamento" column with a date+time, a "State" column
     * that can report a cancelled operation, and dot-decimal amounts.
     */
    public function test_it_imports_a_revolut_style_statement_and_skips_cancelled_operations()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $csv = implode("\n", [
            'Tipo,Prodotto,Data di inizio,Data di completamento,Descrizione,Importo,Costo,Valuta,State,Saldo',
            'Pagamento con carta,Attuale,2026-07-02 09:15:39,2026-07-03 14:01:25,Esselunga,-66.61,0.00,EUR,COMPLETATO,95.44',
            'Ricarica,Attuale,2026-07-07 07:26:05,2026-07-07 07:26:29,Ricarica di *5422,1000.00,0.00,EUR,COMPLETATO,1075.44',
            'Pagamento con carta,Attuale,2026-07-07 16:38:38,,Uber,-22.92,0.00,EUR,OPERAZIONE ANNULLATA,',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('revolut.csv', $csv),
        ]);

        $response->assertRedirect(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 7]));

        // The cancelled Uber row must not be imported.
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseMissing('transactions', ['description' => 'Uber']);

        $groceries = Transaction::where('description', 'Esselunga')->first();
        $this->assertNotNull($groceries);
        $this->assertSame('expense', $groceries->direction);
        $this->assertEquals(66.61, $groceries->amount);
        $this->assertSame('2026-07-03', $groceries->transaction_date->format('Y-m-d'));
    }

    /**
     * Real Revolut statements contain distinct transactions that share the
     * same day, description and amount (e.g. two identical top-ups). Since
     * only "Data di completamento" carries a precise timestamp, the dedup
     * hash must use the full date+time - not just the calendar day - or
     * the second transaction is wrongly dropped as a duplicate.
     */
    public function test_it_does_not_conflate_distinct_same_day_same_amount_transactions()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $csv = implode("\n", [
            'Tipo,Prodotto,Data di inizio,Data di completamento,Descrizione,Importo,Costo,Valuta,State,Saldo',
            'Ricarica,Attuale,2026-07-18 14:27:04,2026-07-18 14:27:06,Ricarica di *5422,120.00,0.00,EUR,COMPLETATO,206.83',
            'Ricarica,Attuale,2026-07-18 17:01:43,2026-07-18 17:01:45,Ricarica di *5422,120.00,0.00,EUR,COMPLETATO,126.83',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('revolut.csv', $csv),
        ]);

        $response->assertRedirect();

        // Both top-ups are real, distinct transactions - neither is a duplicate.
        $this->assertDatabaseCount('transactions', 2);
    }

    /**
     * Mirrors an Italian current-account export: "Data operazione" vs
     * "Data contabile", an "Importo ( € )" header, and quoted descriptions
     * containing commas (e.g. bank transfer references).
     */
    public function test_it_imports_a_current_account_style_statement()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $csv = implode("\n", [
            'Data operazione,Data contabile,Iban,Tipologia,Nome,Descrizione,Importo ( € )',
            '02/07/2026,04/07/2026,IT51I03268223000EMH00911706,Pagamento,Amazon,WWW.AMAZON.IT +14018657948,-58.41',
            '09/07/2026,09/07/2026,IT51I03268223000EMH00911706,Bonifico ordinario,Datore di lavoro,"Mario Rossi    DA TRBKITMMXXX           URI abc123",1500',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('conto.csv', $csv),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 2);

        $amazon = Transaction::where('description', 'WWW.AMAZON.IT +14018657948')->first();
        $this->assertNotNull($amazon);
        $this->assertSame('expense', $amazon->direction);
        $this->assertEquals(58.41, $amazon->amount);
        $this->assertSame('2026-07-02', $amazon->transaction_date->format('Y-m-d'));

        $salary = Transaction::where('amount', 1500)->first();
        $this->assertSame('income', $salary->direction);
    }

    /**
     * Date-only exports (no time component) can contain two genuinely
     * distinct transactions with an identical date+description+amount
     * (e.g. the same merchant charged twice the same day). They must both
     * be kept, but re-importing the same file must still recognise both
     * as duplicates the second time around.
     */
    public function test_it_keeps_repeated_identical_rows_but_still_dedups_on_reimport()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create();

        $csv = implode("\n", [
            'Data operazione,Data contabile,Iban,Tipologia,Nome,Descrizione,Importo ( € )',
            '18/07/2026,20/07/2026,IT51I03268223000EMH00911706,Pagamento,Revolut,Revolut**3251* Dublin,-120.0',
            '18/07/2026,20/07/2026,IT51I03268223000EMH00911706,Pagamento,Revolut,Revolut**3251* Dublin,-120.0',
        ]);

        $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('conto.csv', $csv),
        ]);

        // Both identical-looking rows are real, distinct transactions.
        $this->assertDatabaseCount('transactions', 2);

        $response = $this->actingAs($user)->post(route('transactions.import', $card), [
            'file' => UploadedFile::fake()->createWithContent('conto.csv', $csv),
        ]);

        $response->assertRedirect();

        // Re-importing the identical file must not create two more rows.
        $this->assertDatabaseCount('transactions', 2);
    }
}
