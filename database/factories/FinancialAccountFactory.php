<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
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
            'type' => fake()->randomElement(FinancialAccount::TYPES),
            'bank_name' => fake()->company(),
            'currency' => 'EUR',
            'initial_balance' => fake()->randomFloat(2, 0, 5000),
            'color' => fake()->hexColor(),
            'icon' => null,
            'is_active' => true,
        ];
    }
}
