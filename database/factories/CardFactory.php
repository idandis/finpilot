<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financial_account_id' => FinancialAccount::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(Card::TYPES),
            'last_four_digits' => fake()->numerify('####'),
            'circuit' => fake()->randomElement(['Visa', 'Mastercard', 'Amex']),
            'is_active' => true,
        ];
    }
}
