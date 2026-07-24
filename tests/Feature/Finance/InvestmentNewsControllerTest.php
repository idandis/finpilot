<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\FinancialAccount;
use App\Models\InstrumentPrice;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvestmentNewsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function investmentCard(User $user): Card
    {
        $account = FinancialAccount::factory()->for($user)->create();

        return Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'is_investment_card' => true,
        ]);
    }

    private function ownPosition(User $user, string $isin = 'US0378331005'): void
    {
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => "Buy trade {$isin} Apple Inc, quantity: 2.0",
            'isin' => $isin,
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);
    }

    public function test_a_user_can_refresh_news_for_their_own_position()
    {
        $user = User::factory()->create();
        $this->ownPosition($user);
        InstrumentPrice::factory()->create(['isin' => 'US0378331005', 'code' => 'AAPL', 'exchange' => 'US']);

        config(['services.eodhd.api_key' => 'test-key']);
        Http::fake([
            'eodhd.com/api/news*' => Http::response([
                ['date' => '2026-07-24T09:00:00+00:00', 'title' => 'Apple news', 'link' => 'https://example.com/a'],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('investments.news.refresh', 'US0378331005'));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast');
        $this->assertDatabaseHas('instrument_news', ['isin' => 'US0378331005', 'title' => 'Apple news']);
    }

    public function test_it_does_not_call_the_api_when_the_isin_is_not_resolvable_yet()
    {
        $user = User::factory()->create();
        $this->ownPosition($user);

        config(['services.eodhd.api_key' => 'test-key']);
        Http::fake();

        $response = $this->actingAs($user)->post(route('investments.news.refresh', 'US0378331005'));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast');
        Http::assertNothingSent();
        $this->assertDatabaseCount('instrument_news', 0);
    }

    public function test_it_reports_an_error_when_the_daily_budget_is_insufficient()
    {
        $user = User::factory()->create();
        $this->ownPosition($user);
        InstrumentPrice::factory()->create(['isin' => 'US0378331005', 'code' => 'AAPL', 'exchange' => 'US']);

        config(['services.eodhd.api_key' => 'test-key']);
        config(['services.eodhd.daily_call_budget' => 5]);
        Http::fake();

        $response = $this->actingAs($user)->post(route('investments.news.refresh', 'US0378331005'));

        $response->assertRedirect();
        Http::assertNothingSent();
        $this->assertDatabaseCount('instrument_news', 0);
    }

    public function test_it_returns_404_for_an_isin_the_user_does_not_own()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('investments.news.refresh', 'XX0000000000'));

        $response->assertNotFound();
    }

    public function test_guests_cannot_refresh_news()
    {
        $response = $this->post(route('investments.news.refresh', 'US0378331005'));

        $response->assertRedirect(route('login'));
    }
}
