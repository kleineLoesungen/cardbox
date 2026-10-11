<?php

namespace App\Enums;

use App\Models\Deck;

/**
 * Which way a deck is learned: front → back or back → front. Progress is
 * kept separately per direction.
 */
enum Direction: string
{
    case Forward = 'forward';
    case Backward = 'backward';

    /** "Deutsch → Englisch" for this deck. */
    public function label(Deck $deck): string
    {
        return $this === self::Forward
            ? $deck->frontLabel().' → '.$deck->backLabel()
            : $deck->backLabel().' → '.$deck->frontLabel();
    }

    /** Language code of the question side. */
    public function questionLanguage(Deck $deck): string
    {
        return $this === self::Forward ? $deck->front_language : $deck->back_language;
    }

    /** Language code of the answer side. */
    public function answerLanguage(Deck $deck): string
    {
        return $this === self::Forward ? $deck->back_language : $deck->front_language;
    }
}
