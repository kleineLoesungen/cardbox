<?php

use App\Models\Card;
use App\Models\Deck;

/** htmx request header, as sent by the card sheets. */
function htmx(): array
{
    return ['HX-Request' => 'true'];
}

describe('Karten anlegen und bearbeiten', function () {
    it('legt eine Karte mit mehreren Bedeutungen an und antwortet mit Fragment', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmx())->post("/decks/{$deck->id}/cards", [
            'front' => [' schnell ', ''],
            'back' => ['fast', 'quick', 'fast'],
        ])->assertOk()
            ->assertSee('Gespeichert: schnell – fast')
            ->assertSee('hx-swap-oob="afterbegin:#card-list"', false)
            ->assertDontSee('<html', false);

        $card = Card::sole();
        expect($card->front)->toBe(['schnell'])->and($card->back)->toBe(['fast', 'quick']);
    });

    it('weist eine leere Seite ab, als Meldung ohne Seitenumbau', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmx())->post("/decks/{$deck->id}/cards", ['front' => ['Haus'], 'back' => ['  ']])
            ->assertStatus(422)
            ->assertSee('Hinten fehlt noch ein Wort.');

        expect(Card::count())->toBe(0);
    });

    it('begrenzt Bedeutungen je Seite', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmx())->post("/decks/{$deck->id}/cards", [
            'front' => array_map(fn ($i) => "w{$i}", range(1, 11)),
            'back' => ['x'],
        ])->assertStatus(422);
    });

    it('ändert eine Karte und lässt sie beim Leeren einer Bedeutung schrumpfen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Haus'], 'back' => ['house', 'home']]);

        $this->withHeaders(htmx())->post("/decks/{$deck->id}/cards/{$card->id}", [
            'front' => ['Haus'], 'back' => ['house', ''],
        ])->assertOk()->assertHeader('HX-Trigger', 'sheet-close');

        expect($card->refresh()->back)->toBe(['house']);
    });

    it('löscht eine Karte', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();

        $this->withHeaders(htmx())->post("/decks/{$deck->id}/cards/{$card->id}/delete")
            ->assertOk()->assertSee('0 Karten');

        expect(Card::find($card->id))->toBeNull();
    });

    it('behandelt Karten fremder Stapel wie nicht vorhanden', function () {
        actingAsUser();
        $fremd = Deck::factory()->shared()->create();
        $card = Card::factory()->for($fremd)->create(['front' => ['Haus']]);

        $this->get("/decks/{$fremd->id}/cards/{$card->id}/edit")->assertNotFound();
        $this->post("/decks/{$fremd->id}/cards/{$card->id}", ['front' => ['X'], 'back' => ['Y']])->assertNotFound();
        $this->post("/decks/{$fremd->id}/cards/{$card->id}/delete")->assertNotFound();

        expect($card->refresh()->front)->toBe(['Haus']);
    });

    it('lässt eine Karte nicht über einen anderen eigenen Stapel ansprechen', function () {
        $ich = actingAsUser();
        $meiner = Deck::factory()->for($ich)->create();
        $fremd = Deck::factory()->create();
        $card = Card::factory()->for($fremd)->create();

        $this->post("/decks/{$meiner->id}/cards/{$card->id}/delete")->assertNotFound();

        expect(Card::find($card->id))->not->toBeNull();
    });

    it('weist auf eine schon vorhandene Vorderseite hin', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create(['front' => ['Haus']]);

        $this->get("/decks/{$deck->id}/front-check?front[]=HAUS")->assertSee('Gibt es schon in diesem Stapel');
        $this->get("/decks/{$deck->id}/front-check?front[]=Baum")->assertDontSee('Gibt es schon');
    });
});

describe('Aus anderen Stapeln übernehmen', function () {
    it('findet eigene und geteilte Karten, nie Karten aus fremden privaten Stapeln', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create();
        $eigen = Deck::factory()->for($ich)->create();
        $geteilt = Deck::factory()->shared()->create();
        $privat = Deck::factory()->create();
        Card::factory()->for($eigen)->create(['front' => ['Hausaufgabe'], 'back' => ['homework']]);
        Card::factory()->for($geteilt)->create(['front' => ['Hausschuh'], 'back' => ['slipper']]);
        Card::factory()->for($privat)->create(['front' => ['Hausgeheimnis'], 'back' => ['secret']]);

        $this->get("/decks/{$ziel->id}/adopt?q=haus")
            ->assertSee('Hausaufgabe')
            ->assertSee('Hausschuh')
            ->assertDontSee('Hausgeheimnis');
    });

    it('sucht nur in Stapeln mit denselben Sprachen und nimmt Platzhalter wörtlich', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create();
        $franz = Deck::factory()->shared()->create(['front_language' => 'de', 'back_language' => 'fr']);
        $engl = Deck::factory()->shared()->create();
        Card::factory()->for($franz)->create(['front' => ['Hausfranz'], 'back' => ['maison']]);
        Card::factory()->for($engl)->create(['front' => ['100%'], 'back' => ['hundred percent']]);
        Card::factory()->for($engl)->create(['front' => ['1000'], 'back' => ['thousand']]);

        $this->get("/decks/{$ziel->id}/adopt?q=Hausfranz")->assertDontSee('maison');
        $this->get("/decks/{$ziel->id}/adopt?q=0%25")->assertSee('hundred percent')->assertDontSee('thousand');
    });

    it('übernimmt eine Karte als Kopie und tauscht bei umgekehrten Sprachen die Seiten', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create(['front_language' => 'de', 'back_language' => 'en']);
        $umgekehrt = Deck::factory()->shared()->create(['front_language' => 'en', 'back_language' => 'de']);
        $quelle = Card::factory()->for($umgekehrt)->create(['front' => ['tree'], 'back' => ['Baum']]);

        $this->withHeaders(htmx())->post("/decks/{$ziel->id}/adopt/{$quelle->id}")->assertOk()->assertSee('übernommen');

        $kopie = $ziel->cards()->sole();
        expect($kopie->id)->not->toBe($quelle->id)
            ->and($kopie->front)->toBe(['Baum'])
            ->and($kopie->back)->toBe(['tree']);
    });

    it('übernimmt keine Karte aus einem fremden privaten Stapel, auch nicht per direkter ID', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create();
        $quelle = Card::factory()->for(Deck::factory()->create())->create();

        $this->post("/decks/{$ziel->id}/adopt/{$quelle->id}")->assertNotFound();

        expect($ziel->cards()->count())->toBe(0);
    });

    it('übernimmt nur in eigene Stapel', function () {
        actingAsUser();
        $fremdesZiel = Deck::factory()->shared()->create();
        $quelle = Card::factory()->for(Deck::factory()->shared()->create())->create();

        $this->post("/decks/{$fremdesZiel->id}/adopt/{$quelle->id}")->assertNotFound();
        $this->get("/decks/{$fremdesZiel->id}/adopt?q=xx")->assertNotFound();
    });
});

describe('Grenzfälle', function () {
    it('antwortet auf ungültige Karten-IDs mit 404', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->get("/decks/{$deck->id}/cards/abc/edit")->assertNotFound();
        $this->post("/decks/{$deck->id}/adopt/abc")->assertNotFound();
    });

    it('markiert Treffer, deren Vorderseite schon im Stapel steht', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create();
        Card::factory()->for($ziel)->create(['front' => ['Hund'], 'back' => ['dog']]);
        Card::factory()->for(Deck::factory()->shared()->create())->create(['front' => ['hund'], 'back' => ['hound']]);

        $this->get("/decks/{$ziel->id}/adopt?q=hound")->assertSee('schon im Stapel');
    });

    it('sucht in einzelnen Bedeutungen, nicht im gespeicherten JSON', function () {
        $ich = actingAsUser();
        $ziel = Deck::factory()->for($ich)->create();
        Card::factory()->for(Deck::factory()->for($ich)->create())->create(['front' => ['Haus'], 'back' => ['house', 'home']]);

        $this->get("/decks/{$ziel->id}/adopt?q=".urlencode('", "'))->assertDontSee('Haus');
    });

    it('lehnt Listen statt Text ab, ohne Serverfehler', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->get('/decks?tab=discover&q[]=x&lang[]=de')->assertOk();
        $this->get("/decks/{$deck->id}/adopt?q[]=x")->assertOk();
        $this->get("/decks/{$deck->id}/front-check?front[][]=x")->assertOk();
        $this->from('/decks')->post('/decks', ['name' => ['x'], 'front_language' => 'de', 'back_language' => 'en'])
            ->assertSessionHasErrors('name');
    });
});
