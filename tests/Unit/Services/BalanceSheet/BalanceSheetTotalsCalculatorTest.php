<?php

namespace Tests\Unit\Services\BalanceSheet;

use App\Models\BalanceSheetEntry;
use App\Models\BalanceSheetProfile;
use App\Models\User;
use App\Services\BalanceSheet\BalanceSheetTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_worth_equals_assets_minus_liabilities_plus_cash()
    {
        $user = User::factory()->create();
        $profile = BalanceSheetProfile::forUser($user);
        $profile->update(['cash_balance' => 1000]);

        BalanceSheetEntry::factory()->for($user)->type('asset')->create(['amount' => 200000]);
        BalanceSheetEntry::factory()->for($user)->type('liability')->create(['amount' => 150000]);

        $overview = (new BalanceSheetTotalsCalculator)->overview($user, $profile);

        $this->assertSame(200000.0, $overview['total_assets']);
        $this->assertSame(150000.0, $overview['total_liabilities']);
        $this->assertSame(51000.0, $overview['net_worth']);
    }

    public function test_hourly_value_is_null_when_no_hours_are_consumed()
    {
        $user = User::factory()->create();
        $profile = BalanceSheetProfile::forUser($user);

        BalanceSheetEntry::factory()->for($user)->type('income')->create(['amount' => 2000]);

        $overview = (new BalanceSheetTotalsCalculator)->overview($user, $profile);

        $this->assertNull($overview['hourly_value']);
    }

    public function test_hourly_value_divides_income_by_consumed_hours()
    {
        $user = User::factory()->create();
        $profile = BalanceSheetProfile::forUser($user);

        BalanceSheetEntry::factory()->for($user)->type('income')->create(['amount' => 2000]);
        BalanceSheetEntry::factory()->for($user)->type('time')->create([
            'amount' => null,
            'hours_per_month' => 160,
            'time_kind' => 'consumes',
        ]);

        $overview = (new BalanceSheetTotalsCalculator)->overview($user, $profile);

        $this->assertSame(12.5, $overview['hourly_value']);
    }

    public function test_free_time_accounts_for_base_hours_freed_and_consumed()
    {
        $user = User::factory()->create();
        $profile = BalanceSheetProfile::forUser($user);
        $profile->update(['base_monthly_hours' => 176]);

        BalanceSheetEntry::factory()->for($user)->type('time')->create([
            'amount' => null,
            'hours_per_month' => 160,
            'time_kind' => 'consumes',
        ]);
        BalanceSheetEntry::factory()->for($user)->type('time')->create([
            'amount' => null,
            'hours_per_month' => 10,
            'time_kind' => 'frees',
        ]);

        $overview = (new BalanceSheetTotalsCalculator)->overview($user, $profile);

        $this->assertSame(26.0, $overview['free_time']);
    }
}
