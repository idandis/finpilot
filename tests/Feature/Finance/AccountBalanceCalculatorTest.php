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

    public function test_history_as_of_reconstructs_the_running_balance_for_a_standalone_card()
    {
        $user = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $user->id, 'financial_account_id' => null]);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $card->id,
            'transaction_date' => '2026-01-10',
            'direction' => 'expense',
            'amount' => 40,
        ]);

        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect([$card]), collect(['2026-01-05', '2026-01-10']));

        $this->assertSame(['2026-01-05' => 0.0, '2026-01-10' => -40.0], $history);
    }

    public function test_history_as_of_returns_null_for_every_date_when_there_are_no_cards_at_all()
    {
        $calculator = new AccountBalanceCalculator;
        $history = $calculator->historyAsOf(collect(), collect(['2026-01-05', '2026-01-10']));

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

    public function test_calculate_keeps_each_standalone_cards_balance_in_its_own_bucket()
    {
        $user = User::factory()->create();
        $cardOne = Card::factory()->create(['user_id' => $user->id, 'financial_account_id' => null]);
        $cardTwo = Card::factory()->create(['user_id' => $user->id, 'financial_account_id' => null]);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $cardOne->id,
            'direction' => 'expense',
            'amount' => 100,
        ]);
        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $cardTwo->id,
            'direction' => 'income',
            'amount' => 50,
        ]);

        $calculator = new AccountBalanceCalculator;
        $balances = $calculator->calculate(collect([$cardOne, $cardTwo]));

        $this->assertSame([
            "card:{$cardOne->id}" => -100.0,
            "card:{$cardTwo->id}" => 50.0,
        ], $balances);
    }

    public function test_total_for_combines_linked_accounts_and_standalone_cards()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['initial_balance' => 500]);
        $linkedCard = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);
        $standaloneCard = Card::factory()->create(['user_id' => $user->id, 'financial_account_id' => null]);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $standaloneCard->id,
            'direction' => 'income',
            'amount' => 30,
        ]);

        $calculator = new AccountBalanceCalculator;
        $total = $calculator->totalFor(collect([$linkedCard, $standaloneCard]));

        $this->assertSame(530.0, $total);
    }

    public function test_total_for_returns_null_only_when_there_are_no_cards_at_all()
    {
        $calculator = new AccountBalanceCalculator;

        $this->assertNull($calculator->totalFor(collect()));
    }
}
