<?php

namespace App\Enums;

/**
 * Rollen und ihre Rangfolge.
 *
 * Es gibt genau drei Rollen. Wer eine vierte braucht: docs/RECIPES.md,
 * Rezept „Eine vierte Rolle".
 */
enum Role: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    /** Hoeherer Rang schliesst niedrigere Raenge ein. */
    public function rank(): int
    {
        return match ($this) {
            self::User => 1,
            self::Moderator => 2,
            self::Admin => 3,
        };
    }

    /** `true`, wenn diese Rolle mindestens so viel darf wie `$required`. */
    public function atLeast(Role $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /** Beschriftung fuer die Oberflaeche. */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Benutzer',
            self::Moderator => 'Moderator',
            self::Admin => 'Administrator',
        };
    }
}
