<?php

use App\Models\Card;
use App\Models\Deck;

/*
 * Main meaning and hint (docs/specs/lernplaene-und-karten.md, B1 and B2).
 */

function htmxHeaders(): array
{
    return ['HX-Request' => 'true'];
}

describe('Hauptbedeutung', function () {
    it('zeigt auf der Rückseite die Hauptbedeutung groß und die anderen als „auch:“', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['front_language' => 'de', 'back_language' => 'en']);
        Card::factory()->for($deck)->create(['front' => ['schnell', 'rasch'], 'back' => ['fast', 'quick', 'rapid']]);

        $this->post('/learn/round', ['deck' => $deck->id]);
        skipIntros();

        $this->get('/learn/round')
            ->assertSee('auch: <span lang="en">quick, rapid</span>', false)
            // Question: only the main meaning, never the alternatives.
            ->assertDontSee('rasch')
            // Read aloud: main meanings only.
            ->assertSee('data-answer="fast"', false)
            ->assertSee('data-question="schnell"', false);
    });

    it('zeigt ohne weitere Bedeutungen kein „auch:“', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

        $this->post('/learn/round', ['deck' => $deck->id]);

        $this->get('/learn/round')->assertDontSee('auch:');
    });

    it('speichert eine neue Reihenfolge als neue Hauptbedeutung', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['schnell'], 'back' => ['fast', 'quick']]);

        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards/{$card->id}", [
            'front' => ['schnell'],
            'back' => ['quick', 'fast'],
        ])->assertOk();

        expect($card->fresh()->back)->toBe(['quick', 'fast'])
            ->and($card->fresh()->backText())->toBe('quick');
    });

    it('bietet im Bearbeiten ☆ nur für weitere Bedeutungen an', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['schnell'], 'back' => ['fast', 'quick']]);

        $html = $this->get("/decks/{$deck->id}/cards/{$card->id}/edit")->assertOk()->getContent();

        // Three rows (1 front, 2 back); only the second back row shows ☆.
        expect(substr_count($html, 'data-meaning-row'))->toBe(3)
            ->and(substr_count($html, 'data-make-main'))->toBe(3)
            ->and(preg_match_all('/data-make-main\s*\n?\s*aria-label="Als Hauptbedeutung" title="Als Hauptbedeutung"\s*>/', $html))->toBe(1);
    });
});

describe('Hinweis', function () {
    it('speichert einen Hinweis, kürzt Leerraum und macht aus leer null', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards", ['front' => ['verpassen'], 'back' => ['miss'], 'hint' => '  (Zug)  '])->assertOk();
        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards", ['front' => ['Hund'], 'back' => ['dog'], 'hint' => '   '])->assertOk();

        expect(Card::where('front', json_encode(['verpassen']))->value('hint'))->toBe('(Zug)')
            ->and(Card::where('front', json_encode(['Hund']))->value('hint'))->toBeNull();
    });

    it('ändert und entfernt einen Hinweis beim Bearbeiten', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['verpassen'], 'back' => ['miss'], 'hint' => '(Zug)']);

        $this->get("/decks/{$deck->id}/cards/{$card->id}/edit")->assertSee('value="(Zug)"', false);

        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards/{$card->id}", ['front' => ['verpassen'], 'back' => ['miss'], 'hint' => ''])->assertOk();
        expect($card->fresh()->hint)->toBeNull();
    });

    it('begrenzt den Hinweis auf 100 Zeichen und lehnt Listen ab', function (mixed $hint) {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards", ['front' => ['a'], 'back' => ['b'], 'hint' => $hint])
            ->assertStatus(422);

        expect(Card::count())->toBe(0);
    })->with([str_repeat('x', 101), [['x']]]);

    it('nimmt genau 100 Zeichen an', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->withHeaders(htmxHeaders())->post("/decks/{$deck->id}/cards", ['front' => ['a'], 'back' => ['b'], 'hint' => str_repeat('ä', 100)])->assertOk();

        expect(Card::sole()->hint)->toBe(str_repeat('ä', 100));
    });

    it('zeigt den Hinweis vor dem Umdrehen und in der Kartenliste', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create(['front' => ['verpassen'], 'back' => ['miss'], 'hint' => '(Zug)']);

        $this->get("/decks/{$deck->id}")->assertSee('(Zug)');
        $this->post('/learn/round', ['deck' => $deck->id]);

        // Front face: question, then the hint — before any answer.
        expect($this->get('/learn/round')->getContent())
            ->toMatch('/verpassen<\/p>\s*<p class="text-sm text-muted">\(Zug\)<\/p>/');
    });

    it('nimmt den Hinweis beim Kopieren und Übernehmen mit', function () {
        $ich = actingAsUser();
        $geteilt = Deck::factory()->shared()->create(['front_language' => 'de', 'back_language' => 'en']);
        $card = Card::factory()->for($geteilt)->create(['front' => ['verpassen'], 'back' => ['miss'], 'hint' => '(Zug)']);
        $ziel = Deck::factory()->for($ich)->create(['front_language' => 'en', 'back_language' => 'de']);

        $this->post("/decks/{$geteilt->id}/copy");
        $this->withHeaders(htmxHeaders())->post("/decks/{$ziel->id}/adopt/{$card->id}")->assertOk();

        $kopie = Deck::where('user_id', $ich->id)->whereKeyNot($ziel->id)->sole()->cards()->sole();
        $uebernommen = $ziel->cards()->sole();
        expect($kopie->hint)->toBe('(Zug)')
            ->and($uebernommen->hint)->toBe('(Zug)')
            ->and($uebernommen->front)->toBe(['miss']);
    });
});
