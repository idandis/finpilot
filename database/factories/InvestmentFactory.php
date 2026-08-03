<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
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
            'isin' => strtoupper(fake()->bothify('??##########')),
            'motivation_reasons' => ['undervaluation', 'growth'],
            'motivation_note' => fake()->sentence(),
            'thesis' => fake()->paragraph(),
            'sell_conditions' => fake()->sentence(),
            'time_horizon' => fake()->randomElement(Investment::TIME_HORIZONS),
            'initial_confidence' => fake()->numberBetween(1, 10),
            'current_confidence' => fake()->numberBetween(1, 10),
        ];
    }
}
