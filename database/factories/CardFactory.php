<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        $pair = fake()->randomElement([
            ['Haus', 'house'], ['Baum', 'tree'], ['Hund', 'dog'], ['Katze', 'cat'],
            ['Buch', 'book'], ['Wasser', 'water'], ['schnell', 'fast'], ['groß', 'big'],
        ]);

        return [
            'deck_id' => Deck::factory(),
            'front' => [$pair[0]],
            'back' => [$pair[1]],
        ];
    }
}
