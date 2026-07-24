<?php

namespace Database\Factories;

use App\Models\InstrumentPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentPrice>
 */
class InstrumentPriceFactory extends Factory
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
            'code' => strtoupper(fake()->lexify('????')),
            'exchange' => 'XETRA',
            'resolution_failed' => false,
            'last_price' => fake()->randomFloat(6, 1, 500),
            'currency' => 'EUR',
            'price_date' => fake()->dateTimeBetween('-5 days', 'now')->format('Y-m-d'),
            'fetched_at' => now(),
        ];
    }
}
