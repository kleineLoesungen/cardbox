<?php

use App\Services\AnswerCheckService;

/*
 * Checking typed answers (docs/specs/schreiben-und-erinnern.md, A3). Pure
 * logic; in Feature because the limits come from config/learning.php.
 */

function verdict(array $meanings, string $typed, bool $exact = false): string
{
    return app(AnswerCheckService::class)->check($meanings, $typed, $exact)['verdict'];
}

it('nimmt Groß/Klein, Leerraum und Satzzeichen am Ende nicht genau', function (string $typed) {
    expect(verdict(['the dog'], $typed))->toBe('right');
})->with(['the dog', 'The Dog', '  the   dog ', 'the dog.', 'THE DOG!']);

it('lässt jede Bedeutung gelten und macht Klammern freiwillig', function () {
    expect(verdict(['fast', 'quick', 'rapid'], 'Quick'))->toBe('right')
        ->and(verdict(['(to) miss'], 'miss'))->toBe('right')
        ->and(verdict(['(to) miss'], 'to miss'))->toBe('right')
        ->and(verdict(['der Hund'], 'Hund'))->toBe('wrong')
        ->and(verdict(['(der) Hund'], 'Hund'))->toBe('right')
        ->and(verdict(['colo(u)r'], 'color'))->toBe('right')
        ->and(verdict(['colo(u)r'], 'colour'))->toBe('right')
        ->and(verdict(['(to) look (at)'], 'to look'))->toBe('right')
        ->and(verdict(['(to) look (at)'], 'look at'))->toBe('right')
        ->and(verdict(['(to) look (at)'], 'to look at'))->toBe('right');
});

it('nimmt zerlegte Akzente und Satzzeichen am Rand nicht genau', function () {
    expect(verdict(["cafe\u{0301}"], 'café'))->toBe('right')
        ->and(verdict(["Ma\u{0308}dchen"], 'Mädchen'))->toBe('right')
        ->and(verdict(['¿Qué tal?'], 'qué tal'))->toBe('right')
        ->and(verdict(['¡Hola!'], 'hola'))->toBe('right');

    $check = app(AnswerCheckService::class)->check(['Hello!'], 'helo');
    expect($check['verdict'])->toBe('close')
        ->and(array_values(array_filter($check['parts'], fn ($p) => $p['diff'])))->toBe([['text' => 'l', 'diff' => true]]);
});

it('erkennt fast richtige Antworten', function (array $meanings, string $typed) {
    expect(verdict($meanings, $typed))->toBe('close');
})->with([
    'ein Buchstabe falsch' => [['receive'], 'recieve'],
    'zwei vertauscht' => [['Haus'], 'Hasu'],
    'einer fehlt' => [['Fahrkarte'], 'Farkarte'],
    'ß und ss' => [['Straßenbahn'], 'Strassenbahn'],
    'Akzent' => [['café'], 'cafe'],
    'Umlaut ohne Punkte' => [['Bär'], 'Bar'],
    'lang mit zwei Fehlern' => [['Entschuldigung'], 'Entschuldgunk'],
]);

it('lässt Kurzes, zu viele Fehler, Leeres und Rechnungen nicht als fast gelten', function () {
    expect(verdict(['cat'], 'car'))->toBe('wrong')
        ->and(verdict(['Bahnhof'], 'Banhfo'))->toBe('wrong')
        ->and(verdict(['Entschuldigung'], 'Entshculdgunk'))->toBe('wrong')
        ->and(verdict(['dog'], ''))->toBe('wrong')
        ->and(verdict(['dog'], '   '))->toBe('wrong')
        ->and(verdict(['12'], '13', true))->toBe('wrong')
        ->and(verdict(['1234'], '1243', true))->toBe('wrong')
        ->and(verdict(['12'], ' 12 ', true))->toBe('right');
});

it('markiert genau die abweichenden Stellen, als Text', function () {
    $check = app(AnswerCheckService::class)->check(['receive'], 'recieve');
    expect($check['parts'])->toBe([
        ['text' => 'rec', 'diff' => false], ['text' => 'ei', 'diff' => true], ['text' => 've', 'diff' => false],
    ]);

    $missing = app(AnswerCheckService::class)->check(['Fahrkarte'], 'Farkarte');
    expect($missing['expected'])->toBe('Fahrkarte')
        ->and($missing['parts'])->toBe([['text' => 'Fa', 'diff' => false], ['text' => 'h', 'diff' => true], ['text' => 'rkarte', 'diff' => false]]);

    $html = app(AnswerCheckService::class)->check(['<b>&co'], '<b>&ko');
    expect(implode('', array_column($html['parts'], 'text')))->toBe('<b>&co');
});

it('zeigt bei fast richtig die passende Variante mit Klammern aufgelöst', function () {
    $check = app(AnswerCheckService::class)->check(['(to) recieve', 'get'], 'to receive');
    expect($check)->verdict->toBe('close')->expected->toBe('to recieve')->meaning->toBe('(to) recieve');
});

it('schneidet sehr lange Eingaben ab, ohne Fehler', function () {
    expect(verdict(['dog'], str_repeat('a', 10000)))->toBe('wrong');
});
