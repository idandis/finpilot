<?php

namespace Tests\Feature\Finance;

use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_an_event_and_parses_the_metrics_textarea()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('investments.events.store', $investment), [
            'event_type' => 'earnings_quarterly',
            'title' => 'Q1 FY2027 — Risultati trimestrali',
            'event_date' => '2026-10-28',
            'metrics_raw' => "Ricavi: $65.6B (+18% YoY)\nEPS: $3.30 vs $3.10 atteso\nCrescita Azure: +33%",
            'summary' => 'Trimestre solido, guidance superiore alle attese.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investment_events', [
            'investment_id' => $investment->id,
            'event_type' => 'earnings_quarterly',
            'title' => 'Q1 FY2027 — Risultati trimestrali',
        ]);

        $event = InvestmentEvent::where('investment_id', $investment->id)->firstOrFail();
        $this->assertSame([
            ['label' => 'Ricavi', 'value' => '$65.6B (+18% YoY)'],
            ['label' => 'EPS', 'value' => '$3.30 vs $3.10 atteso'],
            ['label' => 'Crescita Azure', 'value' => '+33%'],
        ], $event->metrics);
    }

    public function test_a_user_cannot_add_an_event_to_another_users_investment()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->post(route('investments.events.store', $investment), [
            'event_type' => 'guidance',
            'title' => 'Tentativo',
            'event_date' => '2026-10-28',
        ]);

        $response->assertForbidden();
    }

    public function test_it_deletes_an_event()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $event = InvestmentEvent::factory()->create(['investment_id' => $investment->id]);

        $response = $this->actingAs($user)->delete(route('investments.events.destroy', $event));

        $response->assertRedirect();
        $this->assertDatabaseMissing('investment_events', ['id' => $event->id]);
    }
}
