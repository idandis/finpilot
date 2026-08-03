<?php

namespace Tests\Feature\Finance;

use App\Models\CompanyAnalysis;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentDecisionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->post(route('investments.decision.store', 'IE00BK5BQT80'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_it_creates_the_decision_journal_for_an_isin()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('investments.decision.store', 'IE00BK5BQT80'), [
            'motivation_reasons' => ['undervaluation', 'growth'],
            'motivation_note' => 'Sembra scontato del 20%',
            'thesis' => 'Crescita solida negli ultimi 5 anni, moat difendibile.',
            'sell_conditions' => 'Deterioramento dei margini o cambio management.',
            'time_horizon' => 'long',
            'initial_confidence' => 8,
        ]);

        $response->assertRedirect(route('investments.positions.show', 'IE00BK5BQT80'));

        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'isin' => 'IE00BK5BQT80',
            'time_horizon' => 'long',
            'initial_confidence' => 8,
            'current_confidence' => 8,
        ]);
    }

    public function test_it_rejects_a_duplicate_decision_journal_for_the_same_isin()
    {
        $user = User::factory()->create();
        Investment::factory()->create(['user_id' => $user->id, 'isin' => 'IE00BK5BQT80']);

        $response = $this->actingAs($user)->post(route('investments.decision.store', 'IE00BK5BQT80'), [
            'thesis' => 'Altra tesi',
            'time_horizon' => 'medium',
            'initial_confidence' => 5,
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(1, Investment::where('user_id', $user->id)->where('isin', 'IE00BK5BQT80')->count());
    }

    public function test_it_updates_the_thesis()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id, 'isin' => 'IE00BK5BQT80']);

        $response = $this->actingAs($user)->patch(route('investments.decision.update', $investment), [
            'thesis' => 'Tesi aggiornata dopo la trimestrale.',
            'current_confidence' => 6,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'thesis' => 'Tesi aggiornata dopo la trimestrale.',
            'current_confidence' => 6,
        ]);
    }

    public function test_it_schedules_the_next_review()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('investments.decision.update', $investment), [
            'next_review_date' => '2026-10-01',
            'next_review_note' => 'Controllare i margini dopo la trimestrale Q3.',
        ]);

        $response->assertRedirect();
        $investment->refresh();
        $this->assertSame('2026-10-01', $investment->next_review_date->toDateString());
        $this->assertSame('Controllare i margini dopo la trimestrale Q3.', $investment->next_review_note);
    }

    public function test_a_user_cannot_update_another_users_investment()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->patch(route('investments.decision.update', $investment), [
            'thesis' => 'Tentativo di modifica',
        ]);

        $response->assertForbidden();
    }

    public function test_it_links_an_existing_company_analysis()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $analysis = CompanyAnalysis::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('investments.decision.link-analysis', $investment), [
            'company_analysis_id' => $analysis->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'company_analysis_id' => $analysis->id,
        ]);
    }

    public function test_it_creates_and_links_a_new_company_analysis_from_a_symbol()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('investments.decision.link-analysis', $investment), [
            'symbol' => 'aapl',
            'name' => 'Apple Inc',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('company_analyses', [
            'user_id' => $user->id,
            'symbol' => 'AAPL',
            'name' => 'Apple Inc',
        ]);
        $investment->refresh();
        $this->assertSame('AAPL', $investment->companyAnalysis->symbol);
    }

    public function test_it_does_not_let_a_user_link_another_users_company_analysis()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $othersAnalysis = CompanyAnalysis::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post(route('investments.decision.link-analysis', $investment), [
            'company_analysis_id' => $othersAnalysis->id,
        ]);

        $response->assertNotFound();
    }
}
