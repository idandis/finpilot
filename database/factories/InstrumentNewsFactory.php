<?php

namespace Database\Factories;

use App\Models\InstrumentNews;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentNews>
 */
class InstrumentNewsFactory extends Factory
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
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'url' => fake()->url(),
            'sentiment_polarity' => fake()->randomFloat(4, -1, 1),
            'tags' => fake()->randomElements(['earnings', 'markets', 'technology', 'm&a'], 2),
        ];
    }
}
