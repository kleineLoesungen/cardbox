<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * Several decks as one ZIP: export and import (docs/specs/stapel-export.md,
 * issue #9).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** A ZIP on disk with the given files (name => content). */
function makeZip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'test-zip-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return $path;
}

/** Exports the decks as the current user and returns the files of the ZIP. */
function exportZip(array $deckIds): array
{
    $response = test()->post('/decks/export', ['decks' => $deckIds])->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $files;
}

/** Uploads a ZIP for the preview, then imports it (as the current user). */
function importZip(array $files): TestResponse
{
    $upload = new UploadedFile(makeZip($files), 'set.zip', 'application/zip', null, true);
    test()->post('/decks/import/preview', ['file' => $upload])->assertOk();

    return test()->post('/decks/import');
}

function overview(array $rows): string
{
    $lines = ['Datei;ID;Name;Beschreibung;Vorne;Hinten;Karten'];
    foreach ($rows as $row) {
        $lines[] = implode(';', $row);
    }

    return implode("\r\n", $lines)."\r\n";
}

function enDeck(User $owner, array $attributes = []): Deck
{
    return Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
}

describe('Export', function () {
    it('packt die gewählten eigenen und gefolgten Stapel mit Übersicht, fremde nicht', function () {
        $ich = actingAsUser();
        $eigen = enDeck($ich, ['name' => 'Unit 3', 'description' => 'ISBN 978-3-12-834211-5']);
        Card::factory()->for($eigen)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $gefolgt = enDeck(User::factory()->create(), ['name' => 'Unit 3', 'is_shared' => true]);
        $ich->followedDecks()->attach($gefolgt);
        $fremd = enDeck(User::factory()->create(), ['name' => 'Fremd', 'is_shared' => true]);

        $files = exportZip([$eigen->id, $gefolgt->id, $fremd->id, 'abc']);

        expect(array_keys($files))->toEqualCanonicalizing(['unit-3_de-en.csv', 'unit-3_de-en-2.csv', 'stapel.csv']);
        $overview = explode("\r\n", trim(substr($files['stapel.csv'], 3)));
        expect($overview[0])->toBe('Datei;ID;Name;Beschreibung;Vorne;Hinten;Karten')
            ->and($files['stapel.csv'])->toContain("{$eigen->id};\"Unit 3\";\"ISBN 978-3-12-834211-5\";de;en;1")
            ->and($files['stapel.csv'])->not->toContain('Fremd')
            ->and(implode('', $files))->toContain('Hund;dog');
    });

    it('verlangt mindestens einen Stapel', function () {
        actingAsUser();

        $this->from('/decks/export')->post('/decks/export', ['decks' => []])
            ->assertSessionHasErrors(['decks' => 'Bitte mindestens einen Stapel wählen.']);
    });
});

describe('Import', function () {
    it('ändert bei der eigenen Rundreise nichts und legt keine Doppelten an', function () {
        $ich = actingAsUser();
        $deck = enDeck($ich, ['name' => 'Unit 3']);
        $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $files = exportZip([$deck->id]);
        $before = $card->fresh()->updated_at;
        $this->travel(1)->hour();

        $upload = new UploadedFile(makeZip($files), 'set.zip', 'application/zip', null, true);
        $this->post('/decks/import/preview', ['file' => $upload])->assertSee('0 neu · 0 geändert · 1 unverändert');
        $this->post('/decks/import')->assertRedirect('/decks')->assertSessionHas('toast.message', '1 Stapel aktualisiert.');

        expect(Deck::count())->toBe(1)->and($card->fresh()->updated_at->equalTo($before))->toBeTrue();
    });

    it('aktualisiert Name, Beschreibung und Karten des eigenen Stapels und behält den Lernstand', function () {
        $ich = actingAsUser();
        $deck = enDeck($ich, ['name' => 'Alt']);
        $hund = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $katze = Card::factory()->for($deck)->create(['front' => ['Katze'], 'back' => ['cat']]);
        $run = app(DeckRunService::class)->forStandard($ich, $deck);
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $hund->id,
            'direction' => Direction::Forward, 'box' => 3, 'due_on' => '2026-10-20', 'review_count' => 3, 'correct_count' => 3]);

        importZip([
            'stapel.csv' => overview([['unit.csv', $deck->id, 'Neu', 'ISBN 123', 'de', 'en', '2']]),
            'unit.csv' => "ID;de;en\n{$hund->id};Hund;dog | hound\n;Maus;mouse\n",
        ])->assertSessionHas('toast.message', '1 Stapel aktualisiert.');

        expect($deck->fresh())->name->toBe('Neu')->description->toBe('ISBN 123')
            ->and($hund->fresh()->back)->toBe(['dog', 'hound'])
            ->and($katze->fresh())->not->toBeNull()
            ->and($deck->cards()->count())->toBe(3)
            ->and(CardProgress::where('card_id', $hund->id)->sole()->box)->toBe(3);
    });

    it('legt für einen gefolgten Stapel einmal eine Kopie an und aktualisiert sie danach', function () {
        $ich = actingAsUser();
        $original = enDeck(User::factory()->create(), ['name' => 'Reisen', 'is_shared' => true]);
        $bahnhof = Card::factory()->for($original)->create(['front' => ['Bahnhof'], 'back' => ['station']]);
        $ich->followedDecks()->attach($original);
        $files = exportZip([$original->id]);

        importZip($files)->assertSessionHas('toast.message', '1 Stapel neu angelegt.');
        $kopie = Deck::where('user_id', $ich->id)->sole();
        expect($kopie)->source_deck_id->toBe($original->id)->is_shared->toBeFalse()
            ->and($kopie->cards()->sole()->source_card_id)->toBe($bahnhof->id);

        importZip($files)->assertSessionHas('toast.message', '1 Stapel aktualisiert.');
        expect(Deck::where('user_id', $ich->id)->count())->toBe(1);
    });

    it('legt bei einer anderen Person alles als eigene private Stapel an', function () {
        $anna = actingAsUser();
        $deck = enDeck($anna, ['name' => 'Unit 3', 'description' => 'ISBN 1', 'is_shared' => true]);
        Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $files = exportZip([$deck->id]);

        $ben = actingAsUser();
        importZip($files)->assertSessionHas('toast.message', '1 Stapel neu angelegt.');

        expect(Deck::where('user_id', $ben->id)->sole())
            ->name->toBe('Unit 3')->description->toBe('ISBN 1')->is_shared->toBeFalse()
            ->and(Deck::where('user_id', $anna->id)->sole()->cards()->count())->toBe(1);
    });

    it('überspringt Stapel mit fremden Sprachen und importiert den Rest', function () {
        $ich = actingAsUser();
        $deck = enDeck($ich);

        $upload = new UploadedFile(makeZip([
            'stapel.csv' => overview([['a.csv', $deck->id, 'A', '', 'de', 'fr', '1'], ['b.csv', '', 'B', '', 'de', 'en', '1']]),
            'a.csv' => "ID;de;fr\n;Hund;chien\n",
            'b.csv' => "ID;de;en\n;Katze;cat\n",
        ]), 'set.zip', 'application/zip', null, true);
        $this->post('/decks/import/preview', ['file' => $upload])->assertSee('Sprachen passen nicht')->assertSee('übersprungen');
        $this->post('/decks/import')->assertSessionHas('toast.message', '1 Stapel neu angelegt, 1 übersprungen.');

        expect($deck->cards()->count())->toBe(0)->and(Deck::where('name', 'B')->sole()->cards()->count())->toBe(1);
    });

    it('macht ohne Übersicht aus jeder CSV einen neuen Stapel und ignoriert macOS-Beiwerk', function () {
        $ich = actingAsUser();

        importZip([
            'Mein Set/englisch-unit-3_de-en.csv' => "ID;de;en\n;Hund;dog\n",
            'Mein Set/mathe_formula-result.csv' => "ID;formula;result\n;6 · 7;42\n",
            '__MACOSX/Mein Set/._englisch-unit-3_de-en.csv' => 'x',
            'Mein Set/notizen.txt' => 'x',
        ])->assertSessionHas('toast.message', '2 Stapel neu angelegt.');

        expect(Deck::where('user_id', $ich->id)->orderBy('name')->pluck('name')->all())->toBe(['Englisch unit 3', 'Mathe'])
            ->and(Deck::where('name', 'Mathe')->sole()->front_language)->toBe('formula');
    });
});

describe('Grenzen', function () {
    it('lehnt kaputte, leere und zu große Dateien ab', function () {
        actingAsUser();
        config(['learning.bundle_max_decks' => 1, 'learning.bundle_max_unpacked_kilobytes' => 1]);

        $kaputt = UploadedFile::fake()->createWithContent('set.zip', 'PK kein zip');
        $this->from('/decks/import')->post('/decks/import/preview', ['file' => $kaputt])->assertSessionHasErrors('file');

        $leer = new UploadedFile(makeZip(['liesmich.txt' => 'x']), 'set.zip', 'application/zip', null, true);
        $this->from('/decks/import')->post('/decks/import/preview', ['file' => $leer])
            ->assertSessionHasErrors(['file' => 'In der Datei wurde kein Stapel gefunden.']);

        $viele = new UploadedFile(makeZip(['a.csv' => "ID;de;en\n", 'b.csv' => "ID;de;en\n"]), 'set.zip', 'application/zip', null, true);
        $this->from('/decks/import')->post('/decks/import/preview', ['file' => $viele])
            ->assertSessionHasErrors(['file' => 'Höchstens 1 Stapel auf einmal.']);

        $gross = new UploadedFile(makeZip(['a.csv' => "ID;de;en\n".str_repeat(";a;b\n", 400)]), 'set.zip', 'application/zip', null, true);
        $this->from('/decks/import')->post('/decks/import/preview', ['file' => $gross])
            ->assertSessionHasErrors(['file' => 'Entpackt ist die Datei größer als 1 KB.']);

        expect(Deck::count())->toBe(0);
    });

    it('importiert nicht ohne vorherige Vorschau', function () {
        actingAsUser();

        $this->post('/decks/import')->assertRedirect('/decks/import')->assertSessionHasErrors('file');
    });

    it('löscht die hochgeladene Datei nach dem Import', function () {
        actingAsUser();

        importZip(['a.csv' => "ID;de;en\n;Hund;dog\n"]);

        expect(Storage::disk('local')->files('imports'))->toBe([]);
    });
});

it('merkt sich beim Kopieren den Originalstapel', function () {
    $ich = actingAsUser();
    $original = enDeck(User::factory()->create(), ['is_shared' => true]);

    $this->post("/decks/{$original->id}/copy");

    expect(Deck::where('user_id', $ich->id)->sole()->source_deck_id)->toBe($original->id);
});
