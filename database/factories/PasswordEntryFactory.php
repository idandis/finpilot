<?php

namespace Database\Factories;

use App\Models\PasswordEntry;
use App\Models\PasswordGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasswordEntry>
 */
class PasswordEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'password_group_id' => PasswordGroup::factory(),
            'platform_name' => fake()->company(),
            'username' => fake()->userName(),
            'password' => fake()->password(12, 20),
        ];
    }
}
