<?php

namespace Database\Factories;

use App\Models\InvestmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentNote>
 */
class InvestmentNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'isin' => strtoupper(fake()->bothify('??##########')),
            'body' => fake()->sentence(),
        ];
    }
}
