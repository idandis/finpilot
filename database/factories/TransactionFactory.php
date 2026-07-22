<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'card_id' => null,
            'transaction_category_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 1, 500),
            'direction' => fake()->randomElement(Transaction::DIRECTIONS),
            'dedup_hash' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
