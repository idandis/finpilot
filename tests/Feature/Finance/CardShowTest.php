<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $card = Card::factory()->for(FinancialAccount::factory())->create();

        $response = $this->get(route('cards.show', $card));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_cannot_view_another_users_card()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for($otherUser)->create();
        $otherCard = Card::factory()->for($otherAccount, 'financialAccount')->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->get(route('cards.show', $otherCard));

        $response->assertForbidden();
    }

    public function test_it_shows_only_transactions_for_the_selected_month()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
        ]);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-07-10',
            'direction' => 'expense',
            'amount' => 20,
        ]);
        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_date' => '2026-08-10',
            'direction' => 'expense',
            'amount' => 30,
        ]);

        $response = $this->actingAs($user)->get(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 7]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('totals.expense', 20)
        );
    }

    public function test_it_shows_a_card_without_a_linked_account()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['financial_account_id' => null]);

        $response = $this->actingAs($user)->get(route('cards.show', $card));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 0)
            ->where('totals.expense', 0)
        );
    }

    public function test_it_shows_transactions_for_a_card_without_a_linked_account()
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['financial_account_id' => null]);

        Transaction::factory()->create([
            'financial_account_id' => null,
            'card_id' => $card->id,
            'transaction_date' => '2026-07-10',
            'direction' => 'expense',
            'amount' => 20,
        ]);

        $response = $this->actingAs($user)->get(route('cards.show', ['card' => $card, 'year' => 2026, 'month' => 7]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('totals.expense', 20)
        );
    }
}
