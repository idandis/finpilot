<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
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
            'user_id' => User::factory(),
            'financial_account_id' => null,
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(Card::TYPES),
            'last_four_digits' => fake()->numerify('####'),
            'circuit' => fake()->randomElement(['Visa', 'Mastercard', 'Amex']),
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement(Card::ICONS),
            'owner_name' => fake()->name(),
            'iban' => null,
            'is_active' => true,
        ];
    }
}
