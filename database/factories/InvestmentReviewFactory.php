<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentReview>
 */
class InvestmentReviewFactory extends Factory
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
            'review_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'decision' => fake()->randomElement(InvestmentReview::DECISIONS),
            'score_before' => fake()->numberBetween(1, 10),
            'score_after' => fake()->numberBetween(1, 10),
            'note' => fake()->sentence(),
        ];
    }
}
