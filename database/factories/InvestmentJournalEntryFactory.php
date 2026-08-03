<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentJournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentJournalEntry>
 */
class InvestmentJournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'entry_type' => 'note',
            'note' => fake()->sentence(),
            'occurred_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
