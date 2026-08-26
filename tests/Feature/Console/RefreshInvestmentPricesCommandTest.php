<?php

namespace Tests\Feature\Console;

use App\Models\Card;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\InstrumentPrice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshInvestmentPricesCommandTest extends TestCase
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

    public function test_it_refreshes_prices_for_open_isins_up_to_the_daily_budget()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'IE00BK5BQT80', 'Vanguard FTSE All-World');
        $this->openPositionTransaction($card, 'IE00B4L5Y983', 'iShares Core MSCI World');
        $this->openPositionTransaction($card, 'US0378331005', 'Apple Inc');

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake([
            'eodhd.com/api/search/*' => Http::response([
                ['Code' => 'XYZ', 'Exchange' => 'XETRA', 'Currency' => 'EUR'],
            ]),
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-22', 'close' => 100.0],
            ]),
        ]);

        $this->artisan('investments:refresh-prices', ['--budget' => 3])
            ->assertSuccessful();

        // Budget of 3 calls covers at most 1 full ISIN (search+eod) plus one
        // more resolve-only call - never more than 3 requests total.
        Http::assertSentCount(3);

        $this->assertDatabaseCount('instrument_prices', 2);
        // Budget is fully spent by the loops above - the history backfill
        // step must not sneak in any extra call. Exactly one row exists
        // here regardless, for whichever single ISIN actually got its close
        // fetched above - appended at no extra API cost alongside its price
        // refresh (see InstrumentPriceHistoryRepository::upsertLatest()).
        $this->assertDatabaseCount('instrument_price_history', 1);
        $this->assertDatabaseHas('instrument_price_history', ['close_price' => 100.0]);
    }

    public function test_it_also_refreshes_the_exchange_rate_for_a_known_non_eur_currency()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'US0378331005', 'Apple Inc');

        // The ISIN's symbol/currency is already resolved from a previous run.
        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'currency' => 'USD',
            'fetched_at' => now()->subHours(25),
        ]);

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake([
            'eodhd.com/api/eod/USDEUR.FOREX*' => Http::response([
                ['date' => '2026-07-23', 'close' => 0.92],
            ]),
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-22', 'close' => 230.0],
            ]),
        ]);

        $this->artisan('investments:refresh-prices', ['--budget' => 5])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'USDEUR.FOREX'));
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate_to_eur' => 0.92]);
    }

    public function test_it_respects_the_global_daily_budget_across_multiple_runs_within_the_same_day()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);
        config(['services.eodhd.daily_call_budget' => 3]);
        config(['services.eodhd.api_key' => 'test-key']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'IE00BK5BQT80', 'Vanguard FTSE All-World');
        $this->openPositionTransaction($card, 'IE00B4L5Y983', 'iShares Core MSCI World');

        Http::fake([
            'eodhd.com/api/search/*' => Http::response([
                ['Code' => 'XYZ', 'Exchange' => 'XETRA', 'Currency' => 'EUR'],
            ]),
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-22', 'close' => 100.0],
            ]),
        ]);

        // First run: the --budget option (18) would allow more, but the
        // global daily limit (3) is what actually caps it.
        $this->artisan('investments:refresh-prices', ['--budget' => 18])->assertSuccessful();
        Http::assertSentCount(3);

        // A second run on the same day - simulating a manual "refresh now"
        // click after the scheduler already ran - must make no further
        // calls: the shared daily budget is already spent.
        $this->artisan('investments:refresh-prices', ['--budget' => 18])->assertSuccessful();
        Http::assertSentCount(3);
        // Still just the one row from the first run's single successful
        // close fetch - the second run made no further calls.
        $this->assertDatabaseCount('instrument_price_history', 1);
    }

    public function test_force_option_refreshes_a_fresh_price_and_rate_anyway()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'US0378331005', 'Apple Inc');

        // Both already resolved and fetched moments ago - well within the
        // normal 24h freshness window.
        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'currency' => 'USD',
            'last_price' => 200,
            'fetched_at' => now(),
            'history_backfilled_at' => now(),
        ]);
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.90,
            'fetched_at' => now(),
            'history_backfilled_at' => now(),
        ]);

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake([
            'eodhd.com/api/eod/USDEUR.FOREX*' => Http::response([
                ['date' => '2026-07-23', 'close' => 0.93],
            ]),
            'eodhd.com/api/eod/*' => Http::response([
                ['date' => '2026-07-23', 'close' => 233.66],
            ]),
        ]);

        $this->artisan('investments:refresh-prices', ['--budget' => 5, '--force' => true])
            ->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'US0378331005', 'last_price' => 233.66]);
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate_to_eur' => 0.93]);
    }

    public function test_without_force_a_fresh_price_is_left_untouched()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'US0378331005', 'Apple Inc');

        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'currency' => 'USD',
            'last_price' => 200,
            'fetched_at' => now(),
            'history_backfilled_at' => now(),
        ]);
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.90,
            'fetched_at' => now(),
            'history_backfilled_at' => now(),
        ]);

        config(['services.eodhd.api_key' => 'test-key']);
        Http::fake();

        $this->artisan('investments:refresh-prices', ['--budget' => 5])
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseHas('instrument_prices', ['isin' => 'US0378331005', 'last_price' => 200]);
    }

    public function test_it_backfills_instrument_and_exchange_rate_history_with_leftover_daily_budget()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $this->openPositionTransaction($card, 'US0378331005', 'Apple Inc');

        // Symbol already resolved and price already fresh - the main loops
        // above spend 0 calls, leaving the whole budget for the backfill.
        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'currency' => 'USD',
            'fetched_at' => now(),
            'history_backfilled_at' => null,
        ]);
        ExchangeRate::factory()->create([
            'currency' => 'USD',
            'rate_to_eur' => 0.92,
            'fetched_at' => now(),
            'history_backfilled_at' => null,
        ]);

        config(['services.eodhd.api_key' => 'test-key']);

        Http::fake([
            'eodhd.com/api/eod/USDEUR.FOREX*' => Http::response([
                ['date' => '2026-07-20', 'close' => 0.90],
                ['date' => '2026-07-21', 'close' => 0.92],
            ]),
            'eodhd.com/api/eod/AAPL.US*' => Http::response([
                ['date' => '2026-07-20', 'close' => 225.0],
                ['date' => '2026-07-21', 'close' => 230.0],
            ]),
        ]);

        $this->artisan('investments:refresh-prices', ['--budget' => 18])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'eod/USDEUR.FOREX')
                && isset($request['from'], $request['to']);
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'eod/AAPL.US')
                && isset($request['from'], $request['to']);
        });

        $this->assertDatabaseCount('instrument_price_history', 2);
        $this->assertDatabaseCount('exchange_rate_history', 2);
        $this->assertNotNull(InstrumentPrice::where('isin', 'US0378331005')->value('history_backfilled_at'));
        $this->assertNotNull(ExchangeRate::where('currency', 'USD')->value('history_backfilled_at'));
    }

    public function test_it_does_nothing_when_there_are_no_open_positions()
    {
        TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Http::fake();

        $this->artisan('investments:refresh-prices')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('instrument_prices', 0);
    }
}
