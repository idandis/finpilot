<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Finance\AccountBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_as_of_reconstructs_the_running_balance_at_each_date()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => 200,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-01-20',
            'direction' => 'income',
            'amount' => 50,
        ]);

        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect([$card]), collect(['2026-01-05', '2026-01-10', '2026-01-15', '2026-01-20']));

        $this->assertSame([
            '2026-01-05' => 1000.0,
            '2026-01-10' => 800.0,
            '2026-01-15' => 800.0,
            '2026-01-20' => 850.0,
        ], $history);
    }

    public function test_history_as_of_returns_null_for_every_date_when_no_account_is_linked()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'financial_account_id' => null]);

        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect([$card]), collect(['2026-01-05', '2026-01-10']));

        $this->assertSame(['2026-01-05' => null, '2026-01-10' => null], $history);
    }

    public function test_history_as_of_sums_multiple_distinct_accounts()
    {
        $user = User::factory()->create();
        $accountOne = FinancialAccount::factory()->for($user)->create(['initial_balance' => 1000]);
        $accountTwo = FinancialAccount::factory()->for($user)->create(['initial_balance' => 2000]);
        $cardOne = Card::factory()->for($accountOne, 'financialAccount')->create(['user_id' => $user->id]);
        $cardTwo = Card::factory()->for($accountTwo, 'financialAccount')->create(['user_id' => $user->id]);

        Transaction::factory()->for($accountOne, 'financialAccount')->create([
            'card_id' => $cardOne->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => 100,
        ]);

        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect([$cardOne, $cardTwo]), collect(['2026-01-05', '2026-01-15']));

        $this->assertSame(['2026-01-05' => 3000.0, '2026-01-15' => 2900.0], $history);
    }

    public function test_history_as_of_includes_a_non_investment_cards_transactions_on_the_same_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 500]);
        $investmentCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => true]);
        $debitCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id, 'is_investment_card' => false]);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $debitCard->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => 50,
        ]);

        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect([$investmentCard]), collect(['2026-01-15']));

        $this->assertSame(['2026-01-15' => 450.0], $history);
    }
}
