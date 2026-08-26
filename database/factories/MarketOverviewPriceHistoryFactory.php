<?php

namespace Database\Factories;

use App\Models\MarketOverviewPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketOverviewPriceHistory>
 */
class MarketOverviewPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instrument_key' => 'sp500',
            'price_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'close_price' => fake()->randomFloat(6, 1, 5000),
        ];
    }
}
