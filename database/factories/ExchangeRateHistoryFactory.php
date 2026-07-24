<?php

namespace Database\Factories;

use App\Models\ExchangeRateHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRateHistory>
 */
class ExchangeRateHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency' => 'USD',
            'rate_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'rate_to_eur' => 0.92,
        ];
    }
}
