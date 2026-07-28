<?php

namespace Tests\Feature\Console;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\InstrumentPrice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshRealtimeInvestmentPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function openPositionTransaction(Card $card, string $isin, string $name): Transaction
    {
        return Transaction::factory()->create([
            'financial_account_id' => $card->financial_account_id,
            'card_id' => $card->id,
            'transaction_category_id' => TransactionCategory::query()->where('name', 'Investimenti')->value('id'),
            'isin' => $isin,
            'quantity' => 2,
            'amount' => 200,
            'direction' => 'expense',
            'description' => "Buy trade {$isin} {$name}, quantity: 2.0",
        ]);
    }

    public function test_it_refreshes_the_intraday_quote_for_an_already_resolved_open_position()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'IE00BK5BQT80', 'Vanguard FTSE All-World');

        InstrumentPrice::factory()->create([
            'isin' => 'IE00BK5BQT80',
            'code' => 'VWCE',
            'exchange' => 'XETRA',
            'currency' => 'EUR',
            'last_price' => 100,
        ]);

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake([
            'eodhd.com/api/real-time/*' => Http::response([
                'code' => 'VWCE.XETRA',
                'timestamp' => now()->timestamp,
                'close' => 103.5,
            ]),
        ]);

        $this->artisan('investments:refresh-realtime-prices', ['--budget' => 5])
            ->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'IE00BK5BQT80', 'realtime_price' => 103.5, 'last_price' => 100]);
    }

    public function test_it_skips_an_instrument_whose_symbol_has_not_been_resolved_yet()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'IE00BK5BQT80', 'Vanguard FTSE All-World');

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake();

        $this->artisan('investments:refresh-realtime-prices', ['--budget' => 5])
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_does_nothing_when_there_are_no_open_positions()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Http::fake();

        $this->artisan('investments:refresh-realtime-prices')->assertSuccessful();

        Http::assertNothingSent();
    }
}
