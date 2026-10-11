<?php

namespace App\Support;

/**
 * Ein Eintrag im Changelog: eine Version mit ihren Änderungen.
 *
 * @see Changelog
 */
final readonly class ChangelogEntry
{
    /**
     * @param  string  $version  Rohfassung, z. B. "2026.10.11" oder "2026.10.11.2"
     * @param  list<string>  $items  Änderungen ohne den führenden Strich
     * @param  list<string>  $migrations  Einträge, die mit "Migration:" beginnen — getrennt,
     *                                    weil sie beim Deployment eine eigene Rolle spielen
     */
    public function __construct(
        public string $version,
        public array $items = [],
        public array $migrations = [],
    ) {}

    /** Version in deutscher Schreibweise: "11.10.2026", mit Zählung "11.10.2026 (2)". */
    public function label(): string
    {
        return Changelog::versionLabel($this->version);
    }
}
