<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentEvent>
 */
class InvestmentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'event_type' => fake()->randomElement(InvestmentEvent::EVENT_TYPES),
            'title' => fake()->sentence(4),
            'event_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'metrics' => [
                ['label' => 'Ricavi', 'value' => '$1.2B (+10% YoY)'],
            ],
            'summary' => fake()->paragraph(),
        ];
    }
}
