<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->post(route('balance-sheet.entries.store', ['type' => 'income']), [
            'name' => 'Stipendio',
            'amount' => 2000,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_a_user_can_create_an_income_entry()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('balance-sheet.entries.store', ['type' => 'income']), [
            'name' => 'Stipendio',
            'category' => 'Lavoro',
            'amount' => 2000,
            'frequency' => 'monthly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('balance_sheet_entries', [
            'user_id' => $user->id,
            'type' => 'income',
            'name' => 'Stipendio',
            'amount' => '2000.00',
            'active' => true,
        ]);
    }

    public function test_a_time_entry_requires_hours_and_time_kind()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('balance-sheet.entries.store', ['type' => 'time']), [
            'name' => 'Pendolarismo',
        ]);

        $response->assertSessionHasErrors(['hours_per_month', 'time_kind']);
    }

    public function test_a_goal_entry_requires_a_progress_percentage()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('balance-sheet.entries.store', ['type' => 'goal']), [
            'name' => 'Fondo emergenza',
        ]);

        $response->assertSessionHasErrors('progress_percent');
    }

    public function test_a_user_cannot_edit_another_users_entry()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $entry = BalanceSheetEntry::factory()->for($owner)->type('income')->create();

        $response = $this->actingAs($intruder)->put(
            route('balance-sheet.entries.update', ['type' => 'income', 'entry' => $entry]),
            ['name' => 'Hackerato', 'amount' => 1],
        );

        $response->assertForbidden();
    }

    public function test_a_user_cannot_delete_another_users_entry()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $entry = BalanceSheetEntry::factory()->for($owner)->type('income')->create();

        $response = $this->actingAs($intruder)->delete(
            route('balance-sheet.entries.destroy', ['type' => 'income', 'entry' => $entry]),
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('balance_sheet_entries', ['id' => $entry->id]);
    }

    public function test_a_user_can_delete_their_own_entry()
    {
        $user = User::factory()->create();
        $entry = BalanceSheetEntry::factory()->for($user)->type('income')->create();

        $response = $this->actingAs($user)->delete(
            route('balance-sheet.entries.destroy', ['type' => 'income', 'entry' => $entry]),
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('balance_sheet_entries', ['id' => $entry->id]);
    }
}
