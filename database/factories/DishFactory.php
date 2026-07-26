<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\User;
use App\Services\Meals\DishCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
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
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(DishCategories::keys()),
        ];
    }
}
