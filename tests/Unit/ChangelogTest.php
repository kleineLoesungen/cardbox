<?php

use App\Support\Changelog;

const BEISPIEL = <<<'MD'
# Änderungen

Ein Vorspann, der übergangen werden muss.

## 2026.10.11.2
- Zweite Veröffentlichung am selben Tag

## 2026.10.11
- Suche in der Benutzerliste
* Auch Sternchen zählen als Stichpunkt
- Migration: 2026_10_11_000000_create_settings_table.php
- migration: klein geschrieben zählt auch

### Unterabschnitt wird übergangen
- Dieser Punkt gehört noch zu 2026.10.11
MD;

it('liest die Einträge in ihrer Reihenfolge', function () {
    $e = Changelog::parse(BEISPIEL);

    expect($e)->toHaveCount(2)
        ->and($e[0]->version)->toBe('2026.10.11.2')
        ->and($e[1]->version)->toBe('2026.10.11');
});

it('stellt Migrationen getrennt', function () {
    $e = Changelog::parse(BEISPIEL);

    expect($e[1]->migrations)->toBe([
        '2026_10_11_000000_create_settings_table.php',
        'klein geschrieben zählt auch',
    ]);

    foreach ($e[1]->items as $punkt) {
        expect($punkt)->not->toStartWith('Migration');
    }
});

it('nimmt Stichpunkte mit Strich und mit Sternchen', function () {
    $e = Changelog::parse(BEISPIEL);

    expect($e[1]->items)
        ->toContain('Suche in der Benutzerliste')
        ->toContain('Auch Sternchen zählen als Stichpunkt');
});

it('eröffnet mit tieferen Überschriften keine Version', function () {
    $e = Changelog::parse(BEISPIEL);

    expect($e[1]->items)->toContain('Dieser Punkt gehört noch zu 2026.10.11');
});

it('macht aus formlosen Eingaben keine Einträge', function (string $text) {
    expect(Changelog::parse($text))->toBe([]);
})->with([
    '',
    'nur Fließtext',
    "## ohne Datum\n- Punkt",
    "## 2026.13\n- zu kurz",
    '- Punkt ohne Überschrift',
]);

it('zählt eine Überschrift mit Zusatztext trotzdem als Version', function () {
    $e = Changelog::parse("## 2026.10.11 — Herbstausgabe\n- Etwas\n");

    expect($e)->toHaveCount(1)
        ->and($e[0]->version)->toBe('2026.10.11')
        ->and($e[0]->items)->toBe(['Etwas']);
});

it('sortiert Versionen richtig', function () {
    expect(Changelog::versionCompare('2026.10.09', '2026.10.10'))->toBeLessThan(0)
        ->and(Changelog::versionCompare('2026.10.09', '2026.10.09.2'))->toBeLessThan(0)
        ->and(Changelog::versionCompare('2026.10.09.2', '2026.10.09.3'))->toBeLessThan(0)
        ->and(Changelog::versionCompare('2026.10.09', '2026.10.09'))->toBe(0)
        ->and(Changelog::versionCompare('2027.01.01', '2026.12.31'))->toBeGreaterThan(0);
});

it('schreibt Versionen für Menschen', function () {
    expect(Changelog::versionLabel('2026.10.11'))->toBe('11.10.2026')
        ->and(Changelog::versionLabel('2026.10.11.2'))->toBe('11.10.2026 (2)')
        ->and(Changelog::versionLabel('kaputt'))->toBe('kaputt');
});

it('hat in der eigenen Datei eine gültige Version', function () {
    // Schlägt fehl, wenn jemand CHANGELOG.md leert oder das Format bricht.
    expect(Changelog::installedVersion())->not->toBeNull()
        ->and(Changelog::installedLabel())->toMatch('/^\d{2}\.\d{2}\.\d{4}/');
});
