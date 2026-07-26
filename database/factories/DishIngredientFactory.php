<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishIngredient;
use App\Services\Shopping\GroceryCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DishIngredient>
 */
class DishIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(GroceryCategories::keys()),
        ];
    }
}
