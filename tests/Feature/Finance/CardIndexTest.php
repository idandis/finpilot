<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('cards.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_a_user_only_sees_cards_from_their_own_accounts()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'name' => 'La mia carta',
        ]);

        $otherUser = User::factory()->create();
        $otherAccount = FinancialAccount::factory()->for($otherUser)->create();
        Card::factory()->for($otherAccount, 'financialAccount')->create([
            'user_id' => $otherUser->id,
            'name' => 'Carta altrui',
        ]);

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('cards', 1)
            ->where('cards.0.name', 'La mia carta')
        );
    }

    public function test_the_page_loads_with_no_cards()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cards.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('cards', 0));
    }
}
