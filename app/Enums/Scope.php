<?php

namespace App\Enums;

/**
 * Scopes — was ein App-Token darf.
 *
 * Ein Token gehoert keinem Benutzer und erbt deshalb auch keine Rolle. Die
 * einzige Antwort auf „was darf dieser Maschinen-Client?" sind seine Scopes.
 *
 * Eine neue Berechtigung hinzufuegen:
 *   1. Fall hier ergaenzen, samt label() und description()
 *   2. Route in routes/api.php mit ->middleware('scope:reports:read') versehen
 *   3. Endpunkt in docs/API.md beschreiben
 *
 * Das Kontrollkaestchen im Token-Formular erscheint automatisch.
 */
enum Scope: string
{
    case NotesRead = 'notes:read';
    case NotesWrite = 'notes:write';
    case UsersRead = 'users:read';

    public function label(): string
    {
        return match ($this) {
            self::NotesRead => 'Notizen lesen',
            self::NotesWrite => 'Notizen schreiben',
            self::UsersRead => 'Benutzer lesen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NotesRead => 'Notizen auflisten und einzeln abrufen',
            self::NotesWrite => 'Notizen anlegen, ändern und löschen',
            self::UsersRead => 'Benutzerliste abrufen (ohne Passwortdaten)',
        };
    }

    /** Beschriftung zu einem gespeicherten Namen; unbekannte Namen bleiben stehen. */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
