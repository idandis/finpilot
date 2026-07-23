<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('accounts.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_their_accounts()
    {
        $user = User::factory()->create();
        FinancialAccount::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('accounts.index'));

        $response->assertOk();
    }

    public function test_users_only_see_their_own_accounts()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        FinancialAccount::factory()->for($otherUser)->create(['name' => 'Conto altrui']);

        $response = $this->actingAs($user)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('accounts', 0));
    }

    public function test_a_user_can_create_a_financial_account()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.store'), [
            'name' => 'Conto corrente principale',
            'type' => 'checking',
            'bank_name' => 'Intesa Sanpaolo',
            'currency' => 'EUR',
            'initial_balance' => 1000,
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('financial_accounts', [
            'user_id' => $user->id,
            'name' => 'Conto corrente principale',
            'type' => 'checking',
        ]);
    }

    public function test_a_user_can_update_their_own_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['name' => 'Vecchio nome']);

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Nuovo nome',
            'type' => $account->type,
            'currency' => $account->currency,
        ]);

        $response->assertRedirect(route('accounts.edit', $account));
        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'name' => 'Nuovo nome',
        ]);
    }

    public function test_updating_an_account_accepts_the_checkbox_value_sent_by_the_edit_form()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['is_active' => true]);

        // Mirrors what a checked native checkbox actually submits (see Edit.vue's is_active field).
        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => $account->type,
            'currency' => $account->currency,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('accounts.edit', $account));
        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'is_active' => true,
        ]);
    }

    public function test_a_user_cannot_update_another_users_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = FinancialAccount::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Nome modificato',
            'type' => $account->type,
            'currency' => $account->currency,
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('accounts.destroy', $account));

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseMissing('financial_accounts', ['id' => $account->id]);
    }

    public function test_a_user_cannot_delete_another_users_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = FinancialAccount::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->delete(route('accounts.destroy', $account));

        $response->assertForbidden();
        $this->assertDatabaseHas('financial_accounts', ['id' => $account->id]);
    }

    public function test_a_user_can_delete_a_card_from_their_account()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('cards.destroy', $card));

        $response->assertRedirect(route('cards.index'));
        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    }

    public function test_a_user_cannot_delete_a_card_from_another_users_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = FinancialAccount::factory()->for($otherUser)->create();
        $card = Card::factory()->for($account, 'financialAccount')->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->delete(route('cards.destroy', $card));

        $response->assertForbidden();
        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }
}
