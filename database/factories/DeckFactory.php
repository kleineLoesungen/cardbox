<?php

namespace Database\Factories;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deck>
 */
class DeckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Unit 1', 'Tiere', 'Farben', 'Verben', 'Lektion 3']),
            'description' => null,
            'front_language' => 'de',
            'back_language' => 'en',
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }
}
