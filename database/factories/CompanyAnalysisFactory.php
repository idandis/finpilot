<?php

namespace Database\Factories;

use App\Models\CompanyAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAnalysis>
 */
class CompanyAnalysisFactory extends Factory
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
            'name' => fake()->company(),
            'symbol' => strtoupper(fake()->lexify('????')).'.US',
        ];
    }
}
