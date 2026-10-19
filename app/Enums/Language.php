<?php

namespace App\Enums;

/**
 * Languages a deck side can have.
 *
 * The language only drives labels, the `lang` attribute of inputs (keyboard,
 * spell check) and read-aloud. Cards store plain text, so adding a case here
 * needs no migration.
 *
 * `formula` and `result` are not languages but let people build arithmetic
 * cards ("3·4" → "12"); `front` and `back` are plain sides for anything else
 * (issue #14). None of the four is read aloud; only arithmetic is checked
 * exactly when typing.
 */
enum Language: string
{
    case German = 'de';
    case English = 'en';
    case French = 'fr';
    case Spanish = 'es';
    case Italian = 'it';
    case Latin = 'la';
    case Turkish = 'tr';
    case Portuguese = 'pt';
    case Russian = 'ru';
    case Chinese = 'zh';
    case Japanese = 'ja';
    case Formula = 'formula';
    case Result = 'result';
    case Front = 'front';
    case Back = 'back';

    public function label(): string
    {
        return match ($this) {
            self::German => 'Deutsch',
            self::English => 'Englisch',
            self::French => 'Französisch',
            self::Spanish => 'Spanisch',
            self::Italian => 'Italienisch',
            self::Latin => 'Latein',
            self::Turkish => 'Türkisch',
            self::Portuguese => 'Portugiesisch',
            self::Russian => 'Russisch',
            self::Chinese => 'Chinesisch',
            self::Japanese => 'Japanisch',
            self::Formula => 'Aufgabe',
            self::Result => 'Ergebnis',
            self::Front => 'Vorderseite',
            self::Back => 'Rückseite',
        };
    }

    /** BCP 47 tag for `lang` attributes and speech synthesis; null when not a language. */
    public function tag(): ?string
    {
        return match ($this) {
            self::Formula, self::Result, self::Front, self::Back => null,
            default => $this->value,
        };
    }

    /** Label for a stored code; unknown codes are shown as they are. */
    public static function labelFor(string $code): string
    {
        return self::tryFrom($code)?->label() ?? $code;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }
}
