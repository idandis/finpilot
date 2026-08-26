<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('balance-sheet.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_also_exposes_the_ledger_data_for_the_side_panel()
    {
        $user = User::factory()->create();
        BalanceSheetEntry::factory()->for($user)->type('income')->create(['name' => 'Stipendio']);

        $response = $this->actingAs($user)->get(route('balance-sheet.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('balance-sheet/Overview/Index')
            ->has('entries', 1)
            ->has('categorySuggestions')
            ->has('liabilityOptions')
            ->has('assetOptions')
        );
    }
}
