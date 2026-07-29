<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\User;
use App\Services\Life\Moods;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
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
            'memory_date' => now()->toDateString(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'location' => fake()->optional()->city(),
            'people' => fake()->optional()->name(),
            'mood' => fake()->randomElement(Moods::keys()),
        ];
    }
}
