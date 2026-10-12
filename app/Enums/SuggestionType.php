<?php

namespace App\Enums;

/** What a suggestion to the owner of a card is about. */
enum SuggestionType: string
{
    case Typo = 'typo';
    case Wrong = 'wrong';
    case Missing = 'missing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Typo => 'Tippfehler',
            self::Wrong => 'Falsche Übersetzung',
            self::Missing => 'Bedeutung fehlt',
            self::Other => 'Sonstiges',
        };
    }
}
