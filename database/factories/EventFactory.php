<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 week', '+2 weeks');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'location' => fake()->optional()->city(),
            'type' => 'evento',
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+1 hour'),
            'all_day' => false,
            'color' => fake()->optional()->hexColor(),
        ];
    }

    /**
     * A reminder: a single point in time, never a duration.
     */
    public function promemoria(): static
    {
        return $this->state(fn () => [
            'type' => 'promemoria',
            'end_at' => null,
            'all_day' => false,
        ]);
    }
}
