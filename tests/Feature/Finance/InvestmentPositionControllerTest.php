<?php

namespace Tests\Feature\Finance;

use App\Models\Card;
use App\Models\CompanyAnalysis;
use App\Models\FinancialAccount;
use App\Models\InstrumentNews;
use App\Models\InstrumentPrice;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\InvestmentNote;
use App\Models\InvestmentReview;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentPositionControllerTest extends TestCase
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

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('investments.positions.show', 'IE00BK5BQT80'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_shows_the_position_detail_page_for_an_isin()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('isin', 'IE00BK5BQT80')
            ->where('instrumentName', 'Vanguard FTSE All-World')
            ->has('positions.open', 1)
            ->where('positions.open.0.invested', 200)
            ->has('transactions', 1)
            ->has('notes', 0)
        );
    }

    public function test_it_returns_404_for_an_isin_the_user_has_never_transacted()
    {
        $user = User::factory()->create();
        $this->investmentCard($user);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'XX0000000000'));

        $response->assertNotFound();
    }

    public function test_it_does_not_include_transactions_from_a_card_not_flagged_as_investment()
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create();
        $regularCard = Card::factory()->for($account, 'financialAccount')->create([
            'user_id' => $user->id,
            'is_investment_card' => false,
        ]);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($account, 'financialAccount')->create([
            'card_id' => $regularCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertNotFound();
    }

    public function test_it_does_not_show_another_users_isin()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCard = $this->investmentCard($otherUser);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($otherCard->financialAccount, 'financialAccount')->create([
            'card_id' => $otherCard->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertNotFound();
    }

    public function test_it_includes_the_users_notes_for_that_isin()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InvestmentNote::factory()->create(['user_id' => $user->id, 'isin' => 'IE00BK5BQT80', 'body' => 'Comprato ai minimi']);
        // Another user's note for the same ISIN must never leak here.
        InvestmentNote::factory()->create(['isin' => 'IE00BK5BQT80', 'body' => 'Nota di un altro utente']);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('notes', 1)
            ->where('notes.0.body', 'Comprato ai minimi')
        );
    }

    public function test_it_includes_cached_news_for_a_resolved_isin()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade US0378331005 Apple Inc, quantity: 2.0',
            'isin' => 'US0378331005',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        InstrumentPrice::factory()->create([
            'isin' => 'US0378331005',
            'code' => 'AAPL',
            'exchange' => 'US',
            'news_fetched_at' => '2026-07-24 08:00:00',
        ]);
        InstrumentNews::factory()->create([
            'isin' => 'US0378331005',
            'title' => 'Apple stock jumps 12% on strong earnings',
            'published_at' => '2026-07-24 07:00:00',
            'sentiment_polarity' => 0.5,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'US0378331005'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('news.resolvable', true)
            ->has('news.articles', 1)
            ->where('news.articles.0.title', 'Apple stock jumps 12% on strong earnings')
            ->has('news.highlights', 1)
            ->where('news.highlights.0.title', 'Apple stock jumps 12% on strong earnings')
            ->where('news.highlights.0.figures.0', '12%')
        );
    }

    public function test_it_reports_news_as_not_resolvable_for_an_unresolved_isin()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('news.resolvable', false)
            ->has('news.articles', 0)
        );
    }

    public function test_it_reports_no_investment_decision_when_none_was_created_yet()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('investment', null)
            ->where('fundamentals', null)
            ->has('journal', 0)
            ->has('motivationOptions')
        );
    }

    public function test_it_includes_the_decision_journal_fundamentals_and_journal_timeline()
    {
        $user = User::factory()->create();
        $card = $this->investmentCard($user);
        $investments = TransactionCategory::factory()->create(['user_id' => null, 'name' => 'Investimenti']);

        Transaction::factory()->for($card->financialAccount, 'financialAccount')->create([
            'card_id' => $card->id,
            'transaction_category_id' => $investments->id,
            'transaction_date' => '2026-01-12',
            'description' => 'Buy trade IE00BK5BQT80 Vanguard FTSE All-World, quantity: 2.0',
            'isin' => 'IE00BK5BQT80',
            'quantity' => 2.0,
            'direction' => 'expense',
            'amount' => 200,
        ]);

        $analysis = CompanyAnalysis::factory()->create(['user_id' => $user->id, 'symbol' => 'VWCE']);
        $investment = Investment::factory()->create([
            'user_id' => $user->id,
            'isin' => 'IE00BK5BQT80',
            'company_analysis_id' => $analysis->id,
            'thesis' => 'ETF diversificato a basso costo.',
            'next_review_date' => '2026-10-01',
            'next_review_note' => 'Controllare i margini dopo la trimestrale Q3.',
        ]);

        $event = InvestmentEvent::factory()->create([
            'investment_id' => $investment->id,
            'title' => 'Q1 FY2027 — Risultati trimestrali',
            'metrics' => [['label' => 'Ricavi', 'value' => '$65.6B (+18% YoY)']],
        ]);
        InvestmentReview::factory()->create([
            'investment_id' => $investment->id,
            'investment_event_id' => $event->id,
            'review_date' => '2026-01-01',
            'thesis_still_valid' => true,
        ]);

        $response = $this->actingAs($user)->get(route('investments.positions.show', 'IE00BK5BQT80'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('investment.id', $investment->id)
            ->where('investment.thesis', 'ETF diversificato a basso costo.')
            ->where('investment.next_review_date', '2026-10-01')
            ->where('investment.next_review_note', 'Controllare i margini dopo la trimestrale Q3.')
            ->where('fundamentals.symbol', 'VWCE')
            ->has('journal', 2)
            ->where('journal.0.type', 'buy')
            ->has('investment.events', 1)
            ->where('investment.events.0.title', 'Q1 FY2027 — Risultati trimestrali')
            ->where('investment.events.0.metrics.0.label', 'Ricavi')
            ->where('investment.reviews.0.investment_event_title', 'Q1 FY2027 — Risultati trimestrali')
            ->where('investment.reviews.0.thesis_still_valid', true)
        );
    }
}
