<?php

namespace Database\Factories;

use App\Models\InstrumentPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentPriceHistory>
 */
class InstrumentPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isin' => strtoupper(fake()->bothify('??##########')),
            'price_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'close_price' => fake()->randomFloat(6, 1, 500),
        ];
    }
}
