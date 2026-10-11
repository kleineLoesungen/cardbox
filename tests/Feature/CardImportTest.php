<?php

use App\Models\Card;
use App\Models\Deck;
use App\Services\CardImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

function parseList(string $text, string $front = 'de', string $back = 'en'): array
{
    $deck = Deck::factory()->make(['front_language' => $front, 'back_language' => $back]);

    return app(CardImportService::class)->parse($deck, $text);
}

describe('Liste zerlegen', function () {
    it('erkennt Gleichheitszeichen, Tabulator und Semikolon', function () {
        $result = parseList("Haus = house\nBaum\ttree\tegal\nHund;dog;tags\n\n");

        expect(array_map(fn ($r) => [$r['front'], $r['back']], $result['rows']))
            ->toBe([['Haus', 'house'], ['Baum', 'tree'], ['Hund', 'dog']])
            ->and($result['errors'])->toBe([]);
    });

    it('teilt beim Gleichheitszeichen nur am ersten', function () {
        $result = parseList('3+4 = 7 = sieben', 'formula', 'result');

        expect($result['rows'][0]['front'])->toBe('3+4')->and($result['rows'][0]['back'])->toBe('7 = sieben');
    });

    it('meldet fehlerhafte Zeilen mit Nummer und übernimmt den Rest', function () {
        $result = parseList("Haus = house\nnur ein Wort\n = leer vorne\nBaum = ");

        expect($result['rows'])->toHaveCount(1)
            ->and(array_column($result['errors'], 'line'))->toBe([2, 3, 4])
            ->and($result['errors'][1]['reason'])->toBe('Vorne fehlt.')
            ->and($result['errors'][2]['reason'])->toBe('Hinten fehlt.');
    });

    it('fasst doppelte Zeilen zusammen', function () {
        expect(parseList("Haus = house\nHaus = house\nHaus = home")['rows'])->toHaveCount(2);
    });

    it('überspringt eine Kopfzeile im vocaBox-Format', function () {
        $result = parseList("de;en;tags\narbeiten;work;verb,basic");

        expect($result['rows'])->toHaveCount(1)
            ->and($result['rows'][0]['front'])->toBe('arbeiten')
            ->and($result['swapped'])->toBeFalse();
    });

    it('tauscht die Seiten bei umgekehrter Kopfzeile', function () {
        $result = parseList("en;de\nwork;arbeiten");

        expect($result['swapped'])->toBeTrue()
            ->and($result['rows'][0]['front'])->toBe('arbeiten')
            ->and($result['rows'][0]['back'])->toBe('work');
    });

    it('lehnt mehr als 1000 Zeilen ab', function () {
        $text = implode("\n", array_map(fn ($i) => "w{$i} = x{$i}", range(1, 1001)));

        expect(fn () => parseList($text))->toThrow(ValidationException::class);
    });
});

describe('Liste einfügen', function () {
    it('zeigt eine Vorschau, ohne etwas anzulegen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->post("/decks/{$deck->id}/import/preview", ['text' => "Haus = house\nfalsch"])
            ->assertOk()
            ->assertSee('1 Karte hinzufügen')
            ->assertSee('Zeile 2');

        expect(Card::count())->toBe(0);
    });

    it('legt alle gültigen Zeilen an', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->post("/decks/{$deck->id}/import", ['text' => "Haus = house\nBaum = tree\nkaputt"])
            ->assertRedirect("/decks/{$deck->id}");

        expect($deck->cards()->pluck('front')->all())->toEqualCanonicalizing([['Haus'], ['Baum']]);
    });

    it('liest eine hochgeladene Datei, auch aus Excel unter Windows', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $file = UploadedFile::fake()->createWithContent('liste.csv', mb_convert_encoding("de;en\nschön;beautiful", 'Windows-1252', 'UTF-8'));

        $this->post("/decks/{$deck->id}/import/preview", ['file' => $file])
            ->assertOk()->assertSee('schön');
    });

    it('lehnt leere Eingaben und fremde Stapel ab', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $fremd = Deck::factory()->shared()->create();

        $this->from("/decks/{$deck->id}/import")->post("/decks/{$deck->id}/import/preview", ['text' => '  '])
            ->assertSessionHasErrors('text');
        $this->post("/decks/{$fremd->id}/import", ['text' => 'a = b'])->assertNotFound();

        expect(Card::count())->toBe(0);
    });
});

describe('Grenzfälle beim Einfügen', function () {
    it('lehnt zu große und falsche Dateien ab', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->from("/decks/{$deck->id}/import")
            ->post("/decks/{$deck->id}/import/preview", ['file' => UploadedFile::fake()->create('gross.txt', 1100, 'text/plain')])
            ->assertSessionHasErrors('file');
        $this->from("/decks/{$deck->id}/import")
            ->post("/decks/{$deck->id}/import/preview", ['file' => UploadedFile::fake()->image('bild.png')])
            ->assertSessionHasErrors(['file' => 'Bitte eine Textdatei (.txt oder .csv) wählen.']);
    });

    it('prüft auch beim Anlegen ohne Vorschau Größe und Form des Textes', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->from("/decks/{$deck->id}/import")
            ->post("/decks/{$deck->id}/import", ['text' => str_repeat("\n", 1024 * 1024 + 1)])
            ->assertSessionHasErrors('text');
        $this->from("/decks/{$deck->id}/import")
            ->post("/decks/{$deck->id}/import", ['text' => ['a = b']])
            ->assertSessionHasErrors('text');

        expect(Card::count())->toBe(0);
    });

    it('liest Unicode-Text aus Excel (UTF-16)', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $utf16 = "\xFF\xFE".mb_convert_encoding("Löwe\tlion", 'UTF-16LE', 'UTF-8');

        $this->post("/decks/{$deck->id}/import/preview", ['file' => UploadedFile::fake()->createWithContent('liste.txt', $utf16)])
            ->assertOk()->assertSee('Löwe');
    });
});
