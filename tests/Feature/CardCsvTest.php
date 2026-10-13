<?php

use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * Cards as CSV: export, and importing an edited export back
 * (docs/specs/csv-und-produktseite.md, stage 1).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function englishDeck(User $owner, array $attributes = []): Deck
{
    return Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
}

/** Export as the current user, without the BOM, split into lines. */
function exportLines(Deck $deck): array
{
    $body = test()->get("/decks/{$deck->id}/export")->assertOk()->getContent();
    expect($body)->toStartWith("\xEF\xBB\xBF");

    return explode("\r\n", rtrim(substr($body, 3), "\r\n"));
}

/** Preview and import a CSV text into a deck (as the current user). */
function importCsv(Deck $deck, string $csv, string $base = '/decks'): TestResponse
{
    test()->post("{$base}/{$deck->id}/import/preview", ['text' => $csv])->assertOk();

    return test()->post("{$base}/{$deck->id}/import", ['text' => $csv]);
}

describe('Export', function () {
    it('schreibt Kopfzeile mit Sprachen, Bedeutungen mit |, Hinweise und maskiert ; und "', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich, ['name' => 'Englisch Unit 3']);
        $a = Card::factory()->for($deck)->create(['front' => ['schnell'], 'back' => ['fast', 'quick'], 'created_at' => now()->subMinute()]);
        $b = Card::factory()->for($deck)->create(['front' => ['sagen; meinen'], 'back' => ['say "so"'], 'hint' => '(Zug)']);

        $response = $this->get("/decks/{$deck->id}/export")
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=englisch-unit-3_de-en.csv');

        expect(exportLines($deck))->toBe([
            'ID;de;en;Hinweis',
            "{$a->id};schnell;\"fast | quick\";",
            "{$b->id};\"sagen; meinen\";\"say \"\"so\"\"\";(Zug)",
        ]);
    });

    it('schützt Felder, die eine Tabellenkalkulation als Formel lesen würde', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['front_language' => 'formula', 'back_language' => 'result']);
        $card = Card::factory()->for($deck)->create(['front' => ['=1+1'], 'back' => ['-ing'], 'hint' => '@home']);

        expect(exportLines($deck))->toBe([
            'ID;formula;result;Hinweis',
            "{$card->id};'=1+1;'-ing;'@home",
        ]);
    });

    it('exportiert geteilte fremde Stapel, aber keine fremden privaten', function () {
        actingAsUser();
        $geteilt = englishDeck(User::factory()->create(), ['is_shared' => true]);
        $privat = englishDeck(User::factory()->create());

        $this->get("/decks/{$geteilt->id}/export")->assertOk();
        $this->get("/decks/{$privat->id}/export")->assertNotFound();
        $this->get('/decks/abc/export')->assertNotFound();
    });
});

describe('Import', function () {
    it('erkennt einen unveränderten Export als „nichts zu ändern“ und schreibt nichts', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);
        $card = Card::factory()->for($deck)->create(['front' => ['schnell'], 'back' => ['fast', 'quick'], 'hint' => '(Tempo)']);
        $csv = implode("\r\n", exportLines($deck));
        $before = $card->fresh()->updated_at;
        $this->travel(1)->hour();

        $this->post("/decks/{$deck->id}/import/preview", ['text' => $csv])
            ->assertSee('0 neu · 0 geändert · 1 unverändert')
            ->assertSee('Nichts zu ändern');
        $this->post("/decks/{$deck->id}/import", ['text' => $csv])
            ->assertSessionHas('toast.message', 'Nichts geändert – alles war schon so.');

        expect($card->fresh()->updated_at->equalTo($before))->toBeTrue();
    });

    it('ändert Karten an Ort und Stelle, legt neue an, löscht nichts und behält den Lernstand', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);
        $hund = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $katze = Card::factory()->for($deck)->create(['front' => ['Katze'], 'back' => ['cat']]);
        $run = app(DeckRunService::class)->forStandard($ich, $deck);
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $hund->id,
            'direction' => Direction::Forward, 'box' => 3, 'due_on' => '2026-10-20', 'review_count' => 3, 'correct_count' => 3]);

        // Katze is missing from the file and must survive.
        $csv = "ID;de;en;Hinweis\r\n{$hund->id};Hund;dog | hound;Tier\r\n;Maus;mouse;\r\n";
        $this->post("/decks/{$deck->id}/import/preview", ['text' => $csv])->assertSee('1 neu · 1 geändert · 0 unverändert');
        $this->post("/decks/{$deck->id}/import", ['text' => $csv])
            ->assertRedirect("/decks/{$deck->id}")
            ->assertSessionHas('toast.message', '1 Karte hinzugefügt, 1 Karte geändert.');

        expect($hund->fresh())->back->toBe(['dog', 'hound'])->hint->toBe('Tier')
            ->and($katze->fresh())->not->toBeNull()
            ->and($deck->cards()->count())->toBe(3)
            ->and(CardProgress::where('card_id', $hund->id)->sole()->box)->toBe(3);
    });

    it('legt Karten mit ID aus einem anderen sichtbaren Stapel neu an, mit Herkunft', function () {
        $ich = actingAsUser();
        $geteilt = englishDeck(User::factory()->create(), ['is_shared' => true]);
        $original = Card::factory()->for($geteilt)->create(['front' => ['Haus'], 'back' => ['house']]);
        $privat = englishDeck(User::factory()->create());
        $versteckt = Card::factory()->for($privat)->create();
        $deck = englishDeck($ich);

        $csv = "ID;de;en\n{$original->id};Haus;house\n{$versteckt->id};Baum;tree\nabc;Ast;branch\n";
        $this->post("/decks/{$deck->id}/import/preview", ['text' => $csv])
            ->assertSee('3 neu')
            ->assertSee('Aus einem anderen Stapel')
            ->assertSee('Unbekannte ID');
        $this->post("/decks/{$deck->id}/import", ['text' => $csv]);

        $haus = $deck->cards()->where('front', json_encode(['Haus']))->sole();
        expect($haus->source_card_id)->toBe($original->id)
            ->and($deck->cards()->where('front', json_encode(['Baum']))->sole()->source_card_id)->toBeNull()
            ->and($versteckt->fresh()->front)->not->toBe(['Baum'])
            ->and($original->fresh()->back)->toBe(['house']);
    });

    it('meldet doppelte IDs, leere Seiten und zu lange Hinweise als Fehlerzeilen', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);
        $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

        $csv = "ID;de;en;Hinweis\n{$card->id};Hund;dog;\n{$card->id};Hund;hound;\n;;leer;\n;Haus;house;".str_repeat('x', 101)."\n";
        $this->post("/decks/{$deck->id}/import/preview", ['text' => $csv])
            ->assertSee('3 Fehler')
            ->assertSee('Diese ID steht schon weiter oben.')
            ->assertSee('Vorne fehlt.')
            ->assertSee('Hinweis zu lang');
    });

    it('versteht Komma und Tab, beliebige Großschreibung und fehlende Hinweisspalte', function (string $sep) {
        $ich = actingAsUser();
        $deck = englishDeck($ich);

        importCsv($deck, "id{$sep}DE{$sep}En\n{$sep}Hund{$sep}dog\n")->assertRedirect("/decks/{$deck->id}");

        expect($deck->cards()->sole())->front->toBe(['Hund'])->back->toBe(['dog'])->hint->toBeNull();
    })->with([',', "\t", ';']);

    it('tauscht bei umgekehrten Sprachen die Seiten, auch bei bekannten Karten', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);
        $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

        importCsv($deck, "ID;en;de\n{$card->id};dog | hound;Hund\n;cat;Katze\n");

        expect($card->fresh()->back)->toBe(['dog', 'hound'])
            ->and($deck->cards()->where('front', json_encode(['Katze']))->sole()->back)->toBe(['cat']);
    });

    it('nimmt Vorne;Hinten ohne Prüfung und bricht bei fremden oder fehlenden Sprachen ab', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);

        importCsv($deck, "ID;Vorne;Hinten\n;Hund;dog\n");
        expect($deck->cards()->count())->toBe(1);

        $this->from("/decks/{$deck->id}/import")->post("/decks/{$deck->id}/import/preview", ['text' => "ID;de;fr\n;Hund;chien\n"])
            ->assertSessionHasErrors(['text' => 'Die Datei ist für Deutsch → Französisch, der Stapel ist Deutsch → Englisch.']);
        $this->from("/decks/{$deck->id}/import")->post("/decks/{$deck->id}/import/preview", ['text' => "ID;Wort;Übersetzung\n;Hund;dog\n"])
            ->assertSessionHasErrors(['text' => 'In der Kopfzeile fehlen die Sprachen, z. B. ID;de;en;Hinweis.']);
    });

    it('entfernt beim Import genau den Formelschutz wieder', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['front_language' => 'formula', 'back_language' => 'result']);
        $card = Card::factory()->for($deck)->create(['front' => ['=1+1'], 'back' => ['-2'], 'hint' => "'zitiert"]);

        importCsv($deck, implode("\r\n", exportLines($deck)));

        expect($card->fresh())->front->toBe(['=1+1'])->back->toBe(['-2'])->hint->toBe("'zitiert");
    });

    it('lässt den alten Import ohne ID-Kopfzeile unverändert', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);

        importCsv($deck, "de;en;tags\nHund;dog;tier\n")
            ->assertSessionHas('toast.message', '1 Karte hinzugefügt.');

        expect($deck->cards()->sole()->back)->toBe(['dog']);
    });

    it('begrenzt auf 1000 Zeilen', function () {
        $ich = actingAsUser();
        $deck = englishDeck($ich);
        $csv = "ID;de;en\n".str_repeat(";a;b\n", 1001);

        $this->from("/decks/{$deck->id}/import")->post("/decks/{$deck->id}/import/preview", ['text' => $csv])
            ->assertSessionHasErrors('text');
    });

    it('importiert nur in eigene Stapel', function () {
        actingAsUser();
        $geteilt = englishDeck(User::factory()->create(), ['is_shared' => true]);

        $this->post("/decks/{$geteilt->id}/import", ['text' => "ID;de;en\n;Hund;dog\n"])->assertNotFound();
        expect($geteilt->cards()->count())->toBe(0);
    });
});

describe('Administratoren', function () {
    it('überschreiben Karten in fremden geteilten Stapeln', function () {
        $admin = actingAsUser(['role' => Role::Admin]);
        $geteilt = englishDeck(User::factory()->create(['name' => 'Anna']), ['is_shared' => true]);
        $card = Card::factory()->for($geteilt)->create(['front' => ['Hund'], 'back' => ['dgo']]);

        $this->get("/decks/{$geteilt->id}")->assertSee("/admin/decks/{$geteilt->id}/import", false);
        $this->get("/admin/decks/{$geteilt->id}/import")->assertOk()->assertSee('Du änderst Karten von Anna');

        importCsv($geteilt, "ID;de;en\n{$card->id};Hund;dog\n", '/admin/decks')
            ->assertRedirect("/decks/{$geteilt->id}");

        expect($card->fresh()->back)->toBe(['dog']);
    });

    it('kommen an fremde private Stapel auch nicht heran', function () {
        actingAsUser(['role' => Role::Admin]);
        $privat = englishDeck(User::factory()->create());

        $this->get("/admin/decks/{$privat->id}/import")->assertNotFound();
        $this->post("/admin/decks/{$privat->id}/import", ['text' => "ID;de;en\n;Hund;dog\n"])->assertNotFound();
        expect($privat->cards()->count())->toBe(0);
    });

    it('sind die Einzigen auf den Admin-Routen', function () {
        actingAsUser(['role' => Role::Moderator]);
        $geteilt = englishDeck(User::factory()->create(), ['is_shared' => true]);

        $this->get("/decks/{$geteilt->id}")->assertDontSee("/admin/decks/{$geteilt->id}/import", false);
        $this->get("/admin/decks/{$geteilt->id}/import")->assertForbidden();
        $this->post("/admin/decks/{$geteilt->id}/import", ['text' => "ID;de;en\n;Hund;dog\n"])->assertForbidden();
        expect($geteilt->cards()->count())->toBe(0);
    });
});
