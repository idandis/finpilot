<?php

namespace Tests\Feature\Finance;

use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\InvestmentReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_a_review_and_moves_the_investments_current_confidence()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id, 'current_confidence' => 5]);

        $response = $this->actingAs($user)->post(route('investments.reviews.store', $investment), [
            'review_date' => '2026-07-01',
            'decision' => 'increase',
            'score_after' => 8,
            'note' => 'Fondamentali in miglioramento, aumento la posizione.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investment_reviews', [
            'investment_id' => $investment->id,
            'decision' => 'increase',
            'score_before' => 5,
            'score_after' => 8,
        ]);
        $this->assertSame(8, $investment->fresh()->current_confidence);
    }

    public function test_it_links_a_review_to_the_event_that_triggered_it()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $event = InvestmentEvent::factory()->create(['investment_id' => $investment->id, 'title' => 'Q1 FY2027 — Risultati trimestrali']);

        $response = $this->actingAs($user)->post(route('investments.reviews.store', $investment), [
            'investment_event_id' => $event->id,
            'review_date' => '2026-10-30',
            'decision' => 'hold',
            'thesis_still_valid' => '1',
            'score_after' => 8,
            'note' => 'Azure e guidance superiori alle attese.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investment_reviews', [
            'investment_id' => $investment->id,
            'investment_event_id' => $event->id,
            'thesis_still_valid' => true,
        ]);
    }

    public function test_it_rejects_an_event_from_another_investment()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $otherInvestment = Investment::factory()->create(['user_id' => $user->id]);
        $foreignEvent = InvestmentEvent::factory()->create(['investment_id' => $otherInvestment->id]);

        $response = $this->actingAs($user)->post(route('investments.reviews.store', $investment), [
            'investment_event_id' => $foreignEvent->id,
            'review_date' => '2026-10-30',
            'decision' => 'hold',
            'score_after' => 5,
        ]);

        $response->assertSessionHasErrors('investment_event_id');
    }

    public function test_a_user_cannot_review_another_users_investment()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->post(route('investments.reviews.store', $investment), [
            'review_date' => '2026-07-01',
            'decision' => 'hold',
            'score_after' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_it_deletes_a_review()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $review = InvestmentReview::factory()->create(['investment_id' => $investment->id]);

        $response = $this->actingAs($user)->delete(route('investments.reviews.destroy', $review));

        $response->assertRedirect();
        $this->assertDatabaseMissing('investment_reviews', ['id' => $review->id]);
    }

    public function test_a_user_cannot_delete_another_users_review()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $owner->id]);
        $review = InvestmentReview::factory()->create(['investment_id' => $investment->id]);

        $response = $this->actingAs($intruder)->delete(route('investments.reviews.destroy', $review));

        $response->assertForbidden();
        $this->assertDatabaseHas('investment_reviews', ['id' => $review->id]);
    }
}
