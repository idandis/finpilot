<?php

namespace Database\Factories;

use App\Models\BalanceSheetProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceSheetProfile>
 */
class BalanceSheetProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cash_balance' => 0,
            'closed_months' => 0,
            'base_monthly_hours' => BalanceSheetProfile::DEFAULT_BASE_MONTHLY_HOURS,
        ];
    }
}
