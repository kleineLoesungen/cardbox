<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
 * "Wörterbuch": looking words up (docs/specs/woerterbuch-und-bereinigung.md,
 * stage 1).
 */

function lookUp(string $term, string $lang = ''): TestResponse
{
    return test()->withHeaders(['HX-Request' => 'true'])
        ->get('/dictionary/search?'.http_build_query(['q' => $term, 'lang' => $lang]));
}

function deDeck(User $owner, array $attributes = []): Deck
{
    return Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
}

it('findet Wörter in eigenen, gefolgten und geteilten Stapeln, nie in fremden privaten', function () {
    $ich = actingAsUser();
    $anna = User::factory()->create(['name' => 'Anna']);
    Card::factory()->for(deDeck($ich, ['name' => 'Meins']))->create(['front' => ['Bahnhof'], 'back' => ['station']]);
    Card::factory()->for(deDeck($anna, ['name' => 'Reisen', 'is_shared' => true]))->create(['front' => ['Bahnsteig'], 'back' => ['platform']]);
    Card::factory()->for(deDeck($anna, ['name' => 'Geheim']))->create(['front' => ['Bahnübergang'], 'back' => ['level crossing']]);

    lookUp('bahn')->assertOk()
        ->assertDontSee('<html', false)
        ->assertSee('Bahnhof')->assertSee('(dein Stapel)')
        ->assertSee('Bahnsteig')->assertSee('von Anna')
        ->assertDontSee('Bahnübergang')->assertDontSee('Geheim');
});

it('sucht in jeder Bedeutung beider Seiten, ohne Groß/Klein, und nimmt Platzhalter wörtlich', function () {
    $ich = actingAsUser();
    $deck = deDeck($ich);
    Card::factory()->for($deck)->create(['front' => ['schnell'], 'back' => ['fast', 'QUICK']]);
    Card::factory()->for($deck)->create(['front' => ['100 %'], 'back' => ['100 percent']]);
    Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

    lookUp('quick')->assertSee('schnell')->assertSee('auch: <span lang="en">QUICK</span>', false);
    lookUp('0 %')->assertSee('100 percent')->assertDontSee('Hund');
    // Not across meaning boundaries: "fast, QUICK" is no match for "t, Q".
    lookUp('t, Q')->assertSee('Nichts gefunden');
});

it('fasst gleiche Wortpaare aus mehreren Stapeln zusammen, auch bei umgekehrten Sprachen', function () {
    $ich = actingAsUser();
    $anna = User::factory()->create();
    Card::factory()->for(deDeck($anna, ['name' => 'A', 'is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['dog']]);
    Card::factory()->for(deDeck($anna, ['name' => 'B', 'is_shared' => true]))->create(['front' => [' hund '], 'back' => ['Dog']]);
    Card::factory()->for(Deck::factory()->for($anna)->shared()->create(['name' => 'C', 'front_language' => 'en', 'back_language' => 'de']))
        ->create(['front' => ['dog'], 'back' => ['Hund']]);
    Card::factory()->for(deDeck($anna, ['name' => 'D', 'is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['hound']]);

    $html = lookUp('hund')->assertSee('in 3 Stapeln')->assertSee('„A“ von', false)->getContent();

    expect(substr_count($html, '<li class="card">'))->toBe(2);
});

it('wartet auf mindestens zwei Zeichen', function () {
    actingAsUser();

    lookUp('a')->assertSee('Nachschlagen statt lernen');
    $this->get('/dictionary')->assertOk()->assertSee('Nachschlagen statt lernen');
});

it('filtert nach Sprache und zeigt nur Sprachen, die es gibt', function () {
    $ich = actingAsUser();
    Card::factory()->for(deDeck($ich))->create(['front' => ['Haus'], 'back' => ['house']]);
    Card::factory()->for(Deck::factory()->for($ich)->create(['front_language' => 'de', 'back_language' => 'fr']))
        ->create(['front' => ['Haus'], 'back' => ['maison']]);

    $this->get('/dictionary')->assertSee('<option value="fr"', false)->assertDontSee('<option value="es"', false);
    lookUp('haus', 'fr')->assertSee('maison')->assertDontSee('house');
    lookUp('haus')->assertSee('maison')->assertSee('house');
});

it('begrenzt auf 30 Treffer und sagt, dass es mehr gibt', function () {
    $ich = actingAsUser();
    $deck = deDeck($ich);
    foreach (range(1, 31) as $i) {
        Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => ["word {$i}"]]);
    }

    $html = lookUp('wort')->assertSee('Es gibt noch mehr Treffer')->getContent();

    expect(substr_count($html, '<li class="card">'))->toBe(30);
});

it('übernimmt einen Treffer nur in eigene Stapel mit passenden Sprachen, mit Herkunft', function () {
    $ich = actingAsUser();
    $original = Card::factory()->for(deDeck(User::factory()->create(), ['is_shared' => true]))->create(['front' => ['Bahnhof'], 'back' => ['station']]);
    $passend = deDeck($ich, ['name' => 'Reisen']);
    $anders = Deck::factory()->for($ich)->create(['name' => 'Französisch', 'front_language' => 'de', 'back_language' => 'fr']);
    $fremd = deDeck(User::factory()->create());

    lookUp('bahnhof')->assertSee('In Stapel übernehmen')->assertSee('„Reisen“', false)->assertDontSee('„Französisch“', false);

    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/adopt/{$original->id}", ['deck' => $passend->id])
        ->assertOk()->assertSee('übernommen');
    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/adopt/{$original->id}", ['deck' => $anders->id])->assertNotFound();
    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/adopt/{$original->id}", ['deck' => $fremd->id])->assertNotFound();

    expect($passend->cards()->sole())->source_card_id->toBe($original->id)->back->toBe(['station'])
        ->and($anders->cards()->count())->toBe(0);
});

it('bietet keinen Stapel an, der das Wortpaar schon enthält', function () {
    $ich = actingAsUser();
    Card::factory()->for(deDeck(User::factory()->create(), ['is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['dog']]);
    Card::factory()->for(deDeck($ich, ['name' => 'Tiere']))->create(['front' => ['Hund'], 'back' => ['dog']]);
    deDeck($ich, ['name' => 'Leer']);

    lookUp('hund')->assertSee('„Leer“', false)->assertDontSee('>„Tiere“<', false);
});

it('bietet Vorlesen in der Sprache jeder Seite an', function () {
    $ich = actingAsUser();
    Card::factory()->for(deDeck($ich))->create(['front' => ['Hund'], 'back' => ['dog']]);

    lookUp('hund')
        ->assertSee('data-speak-text="Hund" data-speak-lang="de"', false)
        ->assertSee('data-speak-text="dog" data-speak-lang="en"', false);
});

it('zeigt in der Kopfzeile das App-Icon', function () {
    actingAsUser();

    $this->get('/learn')->assertSee('<img src="/favicon.svg" alt="" class="size-7">', false);
});

it('ist nur für Angemeldete und steht im Menü', function () {
    $this->get('/dictionary')->assertRedirect('/login');

    actingAsUser();
    $this->get('/learn')->assertSee('href="/dictionary"', false);
    // Also in the bottom bar on the phone, marked active on its own page.
    $this->get('/dictionary')->assertSee('<a href="/dictionary" class="tab tab-active">', false);
});
