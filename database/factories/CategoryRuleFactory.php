<?php

namespace Database\Factories;

use App\Models\CategoryRule;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryRule>
 */
class CategoryRuleFactory extends Factory
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
            'pattern' => fake()->unique()->word(),
            'priority' => 0,
            'times_applied' => 0,
            'is_active' => true,
        ];
    }
}
