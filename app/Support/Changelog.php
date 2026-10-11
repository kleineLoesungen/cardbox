<?php

namespace App\Support;

/**
 * Changelog und Version der laufenden Anwendung.
 *
 * Die Version wird nirgends gepflegt — sie **ist** die oberste Überschrift in
 * `CHANGELOG.md`:
 *
 * ```markdown
 * ## 2026.10.11
 * - Suche in der Benutzerliste
 * - Migration: 2026_10_11_000000_create_projects_table.php
 * ```
 *
 * Es gibt deshalb keine Versionsnummer in `composer.json`, die jemand
 * nachziehen müsste. Die Datei wird zur Laufzeit gelesen und pro Anfrage
 * einmal zwischengespeichert.
 *
 * **Darum muss `CHANGELOG.md` im Abbild liegen** — das `Dockerfile` kopiert
 * das ganze Projekt, `.dockerignore` darf die Datei also nicht ausschließen.
 */
final class Changelog
{
    /** Zwischenspeicher für diese Anfrage: Die Datei ändert sich zur Laufzeit nicht. */
    private static ?string $raw = null;

    /**
     * Zerlegt einen Changelog in Einträge, neueste zuerst.
     *
     * Zeilen, die in kein Muster passen, werden übergangen. Eine formlose Zeile
     * darf die Anwendung nie zum Absturz bringen — sie steht in einer Datei,
     * die Menschen von Hand pflegen.
     *
     * @return list<ChangelogEntry>
     */
    public static function parse(string $markdown): array
    {
        /** @var list<array{version: string, items: list<string>, migrations: list<string>}> $entries */
        $entries = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $trimmed = trim($line);

            $version = self::versionHeading($trimmed);
            if ($version !== null) {
                $entries[] = ['version' => $version, 'items' => [], 'migrations' => []];

                continue;
            }

            // Stichpunkte gehören zur zuletzt gesehenen Version. Steht einer vor
            // der ersten Überschrift, hat er keine Version und entfällt.
            if ($entries === []) {
                continue;
            }

            $text = self::bullet($trimmed);
            if ($text === null) {
                continue;
            }

            $last = count($entries) - 1;
            if (stripos($text, 'Migration:') === 0) {
                $entries[$last]['migrations'][] = trim(substr($text, strlen('Migration:')));
            } else {
                $entries[$last]['items'][] = $text;
            }
        }

        return array_map(
            fn (array $e) => new ChangelogEntry($e['version'], $e['items'], $e['migrations']),
            $entries,
        );
    }

    /**
     * "## 2026.10.11" oder "## 2026.10.11.2" — sonst `null`.
     *
     * Zusatztext nach der Version ist erlaubt ("## 2026.10.11 — Herbstausgabe").
     * Sonst würde so eine Zeile stillschweigend übergangen, ihre Stichpunkte
     * landeten beim älteren Eintrag und die Anwendung meldete weiter die alte
     * Version — ein Fehler, den niemand bemerkt.
     */
    private static function versionHeading(string $line): ?string
    {
        if (! str_starts_with($line, '##')) {
            return null;
        }

        $rest = trim(substr($line, 2));
        // Keine tiefere Ebene: "### …" beginnt nach dem Abschneiden mit "#".
        if ($rest === '' || str_starts_with($rest, '#')) {
            return null;
        }

        $first = preg_split('/\s+/', $rest)[0] ?? '';

        return self::isVersion($first) ? $first : null;
    }

    /** `JJJJ.MM.TT` mit optionaler Zählung. */
    private static function isVersion(string $text): bool
    {
        return (bool) preg_match('/\A\d{4}\.\d{2}\.\d{2}(\.\d+)?\z/', $text);
    }

    /** "- Text" oder "* Text" — sonst `null`. */
    private static function bullet(string $line): ?string
    {
        foreach (['- ', '* '] as $prefix) {
            if (str_starts_with($line, $prefix)) {
                $rest = trim(substr($line, 2));

                return $rest === '' ? null : $rest;
            }
        }

        return null;
    }

    /**
     * Vergleicht zwei Datums-Versionen wie `<=>`:
     * 2026.10.09 < 2026.10.09.2 < 2026.10.10.
     */
    public static function versionCompare(string $a, string $b): int
    {
        return self::parts($a) <=> self::parts($b);
    }

    /** @return array{int, int, int, int} */
    private static function parts(string $version): array
    {
        $values = [0, 0, 0, 0];
        foreach (array_slice(explode('.', $version), 0, 4) as $i => $part) {
            $values[$i] = ctype_digit($part) ? (int) $part : 0;
        }

        /** @var array{int, int, int, int} $values */
        return $values;
    }

    /** "2026.10.11" → "11.10.2026", "2026.10.11.2" → "11.10.2026 (2)". */
    public static function versionLabel(string $version): string
    {
        $parts = explode('.', $version);
        if (count($parts) < 3) {
            return $version;
        }

        $date = "{$parts[2]}.{$parts[1]}.{$parts[0]}";

        return isset($parts[3]) ? "{$date} ({$parts[3]})" : $date;
    }

    /**
     * Alle Einträge dieser Installation, neueste zuerst.
     *
     * @return list<ChangelogEntry>
     */
    public static function entries(): array
    {
        return self::parse(self::raw());
    }

    /** Version dieser Installation, oder `null` bei leerem Changelog. */
    public static function installedVersion(): ?string
    {
        return self::entries()[0]->version ?? null;
    }

    /** Version dieser Installation für die Anzeige, oder `null`. */
    public static function installedLabel(): ?string
    {
        $version = self::installedVersion();

        return $version === null ? null : self::versionLabel($version);
    }

    /** Für Tests: Inhalt setzen (`null` liest wieder die Datei). */
    public static function fake(?string $markdown): void
    {
        self::$raw = $markdown;
    }

    private static function raw(): string
    {
        if (self::$raw === null) {
            // Bewusst nicht base_path(): So lesbar auch ohne laufende Anwendung,
            // etwa in einem Unit-Test. app/Support/ → zwei Ebenen bis zur Wurzel.
            $path = dirname(__DIR__, 2).'/CHANGELOG.md';
            self::$raw = is_file($path) ? (string) file_get_contents($path) : '';
        }

        return self::$raw;
    }
}
