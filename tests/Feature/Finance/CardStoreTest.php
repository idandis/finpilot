<?php

namespace Tests\Feature\Finance;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->post(route('cards.store'), [
            'name' => 'Carta Visa Oro',
            'type' => 'debit',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_can_create_a_card_without_an_account()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Carta Visa Oro',
            'type' => 'debit',
            'last_four_digits' => '1234',
            'circuit' => 'Visa',
            'owner_name' => 'Mario Rossi',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'user_id' => $user->id,
            'financial_account_id' => null,
            'name' => 'Carta Visa Oro',
            'last_four_digits' => '1234',
        ]);
    }

    public function test_a_user_can_create_a_card_linked_to_their_own_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Carta Visa Oro',
            'type' => 'debit',
            'financial_account_id' => $account->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'name' => 'Carta Visa Oro',
        ]);
    }

    public function test_a_user_cannot_link_a_card_to_another_users_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->post(route('cards.store'), [
            'name' => 'Carta Visa Oro',
            'type' => 'debit',
            'financial_account_id' => $otherAccount->id,
        ]);

        $response->assertSessionHasErrors('financial_account_id');
        $this->assertDatabaseMissing('cards', ['name' => 'Carta Visa Oro']);
    }
}
