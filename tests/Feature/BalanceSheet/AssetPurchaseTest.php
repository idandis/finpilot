<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_buying_an_asset_creates_the_linked_entries_and_uses_cash()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 100000]);

        $response = $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'category' => 'Immobili affitto',
            'asset_value' => 200000,
            'monthly_income' => 850,
            'cash_used' => 50000,
            'mortgage_total' => 150000,
            'monthly_installment' => 700,
            'hours_per_month' => 3,
        ]);

        $response->assertRedirect();

        $asset = BalanceSheetEntry::query()->where('type', 'asset')->firstOrFail();
        $this->assertSame('Appartamento via Garibaldi', $asset->name);
        $this->assertSame('200000.00', $asset->amount);

        $income = BalanceSheetEntry::query()->where('type', 'income')->where('linked_asset_id', $asset->id)->firstOrFail();
        $this->assertSame('850.00', $income->amount);

        $liability = BalanceSheetEntry::query()->where('type', 'liability')->where('linked_asset_id', $asset->id)->firstOrFail();
        $this->assertSame('150000.00', $liability->amount);

        $expense = BalanceSheetEntry::query()->where('type', 'expense')->where('linked_asset_id', $asset->id)->firstOrFail();
        $this->assertSame('700.00', $expense->amount);
        $this->assertSame($liability->id, $expense->linked_liability_id);

        $time = BalanceSheetEntry::query()->where('type', 'time')->where('linked_asset_id', $asset->id)->firstOrFail();
        $this->assertSame('3.00', $time->hours_per_month);
        $this->assertSame('consumes', $time->time_kind);

        $this->assertSame('50000.00', BalanceSheetProfile::forUser($user)->fresh()->cash_balance);
    }

    public function test_updating_an_asset_updates_linked_entries_and_cash_balance()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 100000]);

        $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
            'monthly_income' => 850,
            'cash_used' => 50000,
        ]);

        $asset = BalanceSheetEntry::query()->where('type', 'asset')->firstOrFail();

        $response = $this->actingAs($user)->put(route('balance-sheet.assets.update', $asset), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
            'monthly_income' => 950,
            'cash_used' => 60000,
        ]);

        $response->assertRedirect();

        $income = BalanceSheetEntry::query()->where('type', 'income')->where('linked_asset_id', $asset->id)->firstOrFail();
        $this->assertSame('950.00', $income->amount);

        // Liquidità nuova = 50000 (dopo il primo acquisto) + 50000 (precedente) - 60000 (nuova) = 40000
        $this->assertSame('40000.00', BalanceSheetProfile::forUser($user)->fresh()->cash_balance);
    }

    public function test_deleting_an_asset_removes_linked_entries_and_restores_cash()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 100000]);

        $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
            'monthly_income' => 850,
            'cash_used' => 50000,
            'mortgage_total' => 150000,
            'monthly_installment' => 700,
            'hours_per_month' => 3,
        ]);

        $asset = BalanceSheetEntry::query()->where('type', 'asset')->firstOrFail();

        $response = $this->actingAs($user)->delete(route('balance-sheet.assets.destroy', $asset));

        $response->assertRedirect();
        $this->assertDatabaseCount('balance_sheet_entries', 0);
        $this->assertSame('100000.00', BalanceSheetProfile::forUser($user)->fresh()->cash_balance);
    }

    public function test_a_user_cannot_edit_another_users_asset()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
        ]);

        $asset = BalanceSheetEntry::query()->where('type', 'asset')->firstOrFail();

        $response = $this->actingAs($intruder)->put(route('balance-sheet.assets.update', $asset), [
            'name' => 'Hackerato',
            'asset_value' => 1,
        ]);

        $response->assertForbidden();
    }
}
