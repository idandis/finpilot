<?php

namespace Database\Factories;

use App\Models\BalanceSheetEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceSheetEntry>
 */
class BalanceSheetEntryFactory extends Factory
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
            'type' => 'income',
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['Lavoro', 'Extra']),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'cash_used' => null,
            'hours_per_month' => null,
            'time_kind' => null,
            'frequency' => 'monthly',
            'progress_percent' => null,
            'active' => true,
            'linked_asset_id' => null,
            'linked_liability_id' => null,
        ];
    }

    public function type(string $type): self
    {
        return $this->state(['type' => $type]);
    }
}
