<?php

namespace Tests\Feature\Finance;

use App\Models\Investment;
use App\Models\InvestmentJournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentJournalEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_a_standalone_journal_entry()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('investments.journal.store', $investment), [
            'note' => 'Ho riletto la tesi dopo la trimestrale, nulla cambia.',
            'occurred_at' => '2026-07-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('investment_journal_entries', [
            'investment_id' => $investment->id,
            'note' => 'Ho riletto la tesi dopo la trimestrale, nulla cambia.',
            'transaction_id' => null,
        ]);
    }

    public function test_a_user_cannot_add_a_journal_entry_to_another_users_investment()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->post(route('investments.journal.store', $investment), [
            'note' => 'Tentativo',
            'occurred_at' => '2026-07-15',
        ]);

        $response->assertForbidden();
    }

    public function test_it_deletes_a_journal_entry()
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->create(['user_id' => $user->id]);
        $entry = InvestmentJournalEntry::factory()->create(['investment_id' => $investment->id]);

        $response = $this->actingAs($user)->delete(route('investments.journal.destroy', $entry));

        $response->assertRedirect();
        $this->assertDatabaseMissing('investment_journal_entries', ['id' => $entry->id]);
    }
}
