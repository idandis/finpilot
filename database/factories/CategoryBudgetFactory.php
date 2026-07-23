<?php

namespace Database\Factories;

use App\Models\CategoryBudget;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryBudget>
 */
class CategoryBudgetFactory extends Factory
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
            'transaction_category_id' => TransactionCategory::factory(),
            'monthly_amount' => fake()->randomFloat(2, 20, 500),
        ];
    }
}
