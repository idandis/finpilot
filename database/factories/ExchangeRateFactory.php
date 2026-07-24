<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
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
            'rate_to_eur' => 0.92,
            'rate_date' => fake()->dateTimeBetween('-5 days', 'now')->format('Y-m-d'),
            'fetched_at' => now(),
        ];
    }
}
