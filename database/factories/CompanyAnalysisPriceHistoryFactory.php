<?php

namespace Database\Factories;

use App\Models\CompanyAnalysisPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAnalysisPriceHistory>
 */
class CompanyAnalysisPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => strtoupper(fake()->lexify('????')).'.US',
            'price_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'close_price' => fake()->randomFloat(6, 1, 500),
        ];
    }
}
