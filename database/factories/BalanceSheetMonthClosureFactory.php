<?php

namespace Database\Factories;

use App\Models\BalanceSheetMonthClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceSheetMonthClosure>
 */
class BalanceSheetMonthClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $income = $this->faker->randomFloat(2, 1000, 4000);
        $expense = $this->faker->randomFloat(2, 500, 3000);

        return [
            'user_id' => User::factory(),
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'income_total' => $income,
            'expense_total' => $expense,
            'cash_flow' => $income - $expense,
            'cash_balance_after' => $this->faker->randomFloat(2, 0, 10000),
        ];
    }
}
