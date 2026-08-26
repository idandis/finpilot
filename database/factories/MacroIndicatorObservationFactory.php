<?php

namespace Database\Factories;

use App\Models\MacroIndicatorObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MacroIndicatorObservation>
 */
class MacroIndicatorObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_key' => 'us_cpi_yoy',
            'observation_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'value' => fake()->randomFloat(6, -5, 10),
            'published_at' => null,
        ];
    }
}
