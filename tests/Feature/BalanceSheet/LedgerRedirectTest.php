<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every balance-sheet mutation route accepts a `from_ledger` query flag so
 * the ledger page (a single-page view that adds/edits/deletes records
 * inline via dialogs) can reuse the very same store/update/destroy
 * endpoints as the classic per-type pages, while staying put instead of
 * navigating to the classic index/edit page.
 */
class LedgerRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_entry_from_the_ledger_redirects_back_to_it()
    {
        $user = User::factory()->create();
        $ledgerUrl = route('balance-sheet.ledger');

        $response = $this->actingAs($user)
            ->from($ledgerUrl)
            ->post(route('balance-sheet.entries.store', ['type' => 'income']).'?from_ledger=1', [
                'name' => 'Stipendio',
                'amount' => 2000,
            ]);

        $response->assertRedirect($ledgerUrl);
        $this->assertDatabaseHas('balance_sheet_entries', ['user_id' => $user->id, 'name' => 'Stipendio']);
    }

    public function test_creating_an_entry_without_the_flag_redirects_to_the_classic_index()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('balance-sheet.entries.store', ['type' => 'income']), [
            'name' => 'Stipendio',
            'amount' => 2000,
        ]);

        $response->assertRedirect(route('balance-sheet.entries.index', 'income'));
    }

    public function test_updating_and_deleting_an_entry_from_the_ledger_redirects_back_to_it()
    {
        $user = User::factory()->create();
        $entry = BalanceSheetEntry::factory()->for($user)->type('income')->create();
        $ledgerUrl = route('balance-sheet.ledger');

        $updateResponse = $this->actingAs($user)
            ->from($ledgerUrl)
            ->put(route('balance-sheet.entries.update', ['type' => 'income', 'entry' => $entry]).'?from_ledger=1', [
                'name' => 'Stipendio aggiornato',
                'amount' => 2500,
            ]);
        $updateResponse->assertRedirect($ledgerUrl);

        $deleteResponse = $this->actingAs($user)
            ->from($ledgerUrl)
            ->delete(route('balance-sheet.entries.destroy', ['type' => 'income', 'entry' => $entry]).'?from_ledger=1');
        $deleteResponse->assertRedirect($ledgerUrl);
        $this->assertDatabaseMissing('balance_sheet_entries', ['id' => $entry->id]);
    }

    public function test_asset_mutations_from_the_ledger_redirect_back_to_it()
    {
        $user = User::factory()->create();
        $ledgerUrl = route('balance-sheet.ledger');

        $storeResponse = $this->actingAs($user)
            ->from($ledgerUrl)
            ->post(route('balance-sheet.assets.store').'?from_ledger=1', [
                'name' => 'Garage',
                'asset_value' => 20000,
            ]);
        $storeResponse->assertRedirect($ledgerUrl);

        $asset = BalanceSheetEntry::query()->where('type', 'asset')->firstOrFail();

        $updateResponse = $this->actingAs($user)
            ->from($ledgerUrl)
            ->put(route('balance-sheet.assets.update', $asset).'?from_ledger=1', [
                'name' => 'Garage',
                'asset_value' => 25000,
            ]);
        $updateResponse->assertRedirect($ledgerUrl);

        $destroyResponse = $this->actingAs($user)
            ->from($ledgerUrl)
            ->delete(route('balance-sheet.assets.destroy', $asset).'?from_ledger=1');
        $destroyResponse->assertRedirect($ledgerUrl);
        $this->assertDatabaseMissing('balance_sheet_entries', ['id' => $asset->id]);
    }

    public function test_closing_the_month_from_the_ledger_redirects_back_to_it()
    {
        $user = User::factory()->create();
        $ledgerUrl = route('balance-sheet.ledger');

        $response = $this->actingAs($user)
            ->from($ledgerUrl)
            ->post(route('balance-sheet.close-month').'?from_ledger=1');

        $response->assertRedirect($ledgerUrl);
    }
}
