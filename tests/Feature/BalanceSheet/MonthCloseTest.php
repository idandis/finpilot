<?php

namespace Tests\Feature\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetMonthClosure;
use App\Models\BalanceSheetProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_the_month_records_the_period()
    {
        $user = User::factory()->create();
        BalanceSheetEntry::factory()->for($user)->type('income')->create(['amount' => 1200]);

        $this->travelTo('2026-08-24 09:00:00');
        $this->actingAs($user)->post(route('balance-sheet.close-month'));

        $this->assertDatabaseHas('balance_sheet_month_closures', [
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 8,
        ]);
    }

    public function test_undoing_the_closure_restores_cash_liabilities_and_entries()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 1000]);

        $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
            'monthly_income' => 850,
            'mortgage_total' => 150000,
            'monthly_installment' => 700,
        ]);

        $bonus = BalanceSheetEntry::factory()->for($user)->type('income')->create([
            'name' => 'Bonus',
            'amount' => 500,
            'frequency' => 'one_time',
        ]);

        $this->actingAs($user)->post(route('balance-sheet.close-month'));

        $closure = BalanceSheetMonthClosure::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertFalse($bonus->fresh()->active);

        $this->actingAs($user)
            ->delete(route('balance-sheet.close-month.destroy', $closure))
            ->assertSessionHasNoErrors();

        $profile = BalanceSheetProfile::forUser($user)->fresh();
        $this->assertSame('1000.00', $profile->cash_balance);
        $this->assertSame(0, $profile->closed_months);

        $liability = BalanceSheetEntry::query()->where('type', 'liability')->firstOrFail();
        $this->assertSame('150000.00', $liability->amount);
        $this->assertTrue($bonus->fresh()->active);
        $this->assertDatabaseCount('balance_sheet_month_closures', 0);
    }

    public function test_only_the_latest_closure_can_be_undone()
    {
        $user = User::factory()->create();

        $older = BalanceSheetMonthClosure::factory()->for($user)->create(['year' => 2026, 'month' => 6]);
        BalanceSheetMonthClosure::factory()->for($user)->create(['year' => 2026, 'month' => 7]);

        $this->actingAs($user)->delete(route('balance-sheet.close-month.destroy', $older));

        $this->assertDatabaseHas('balance_sheet_month_closures', ['id' => $older->id]);
    }

    public function test_it_refuses_to_undo_a_closure_of_another_user()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $closure = BalanceSheetMonthClosure::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->delete(route('balance-sheet.close-month.destroy', $closure))
            ->assertForbidden();

        $this->assertDatabaseHas('balance_sheet_month_closures', ['id' => $closure->id]);
    }

    public function test_closing_the_month_updates_cash_balance_and_pays_down_the_mortgage()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 1000]);

        $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Appartamento via Garibaldi',
            'asset_value' => 200000,
            'monthly_income' => 850,
            'mortgage_total' => 150000,
            'monthly_installment' => 700,
        ]);

        $this->actingAs($user)->post(route('balance-sheet.entries.store', ['type' => 'expense']), [
            'name' => 'Spesa',
            'amount' => 300,
        ]);

        $response = $this->actingAs($user)->post(route('balance-sheet.close-month'));
        $response->assertRedirect();

        $profile = BalanceSheetProfile::forUser($user)->fresh();
        // 1000 + (850 entrata - 700 rata - 300 spesa) = 850
        $this->assertSame('850.00', $profile->cash_balance);
        $this->assertSame(1, $profile->closed_months);

        $liability = BalanceSheetEntry::query()->where('type', 'liability')->firstOrFail();
        $this->assertSame('149300.00', $liability->amount);
        $this->assertTrue($liability->active);

        $this->assertDatabaseHas('balance_sheet_month_closures', [
            'user_id' => $user->id,
            'income_total' => '850.00',
            'expense_total' => '1000.00',
            'cash_flow' => '-150.00',
            'cash_balance_after' => '850.00',
        ]);
    }

    public function test_a_fully_paid_mortgage_becomes_inactive_and_stops_reducing_cash_flow()
    {
        $user = User::factory()->create();
        BalanceSheetProfile::forUser($user)->update(['cash_balance' => 0]);

        $this->actingAs($user)->post(route('balance-sheet.assets.store'), [
            'name' => 'Garage',
            'asset_value' => 20000,
            'mortgage_total' => 1000,
            'monthly_installment' => 700,
        ]);

        // First close: 1000 - 700 = 300 remaining.
        $this->actingAs($user)->post(route('balance-sheet.close-month'));

        // Second close: liability drops to 0 and is extinguished, installment deactivates.
        $this->actingAs($user)->post(route('balance-sheet.close-month'));

        $liability = BalanceSheetEntry::query()->where('type', 'liability')->firstOrFail();
        $expense = BalanceSheetEntry::query()->where('type', 'expense')->firstOrFail();

        $this->assertSame('0.00', $liability->amount);
        $this->assertFalse($liability->active);
        $this->assertFalse($expense->active);

        $closuresCount = BalanceSheetMonthClosure::query()->where('user_id', $user->id)->count();
        $this->assertSame(2, $closuresCount);

        // A third close should no longer touch the extinguished liability.
        $this->actingAs($user)->post(route('balance-sheet.close-month'));
        $this->assertSame('0.00', $liability->fresh()->amount);
    }
}
