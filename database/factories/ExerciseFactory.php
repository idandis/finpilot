<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\User;
use App\Services\Workouts\ExerciseCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
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
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(ExerciseCategories::keys()),
            'requires_equipment' => fake()->boolean(),
        ];
    }
}
